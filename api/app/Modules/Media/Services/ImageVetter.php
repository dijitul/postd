<?php

namespace App\Modules\Media\Services;

use App\Models\BusinessImage;
use Illuminate\Support\Facades\Http;

/**
 * Looks at a library photo once and says what it is.
 *
 * Filters on file names, size and position caught logos and icons but not
 * the images that matter most: the first real import pulled in screenshots
 * of client websites, a slide of words and near-duplicates, all of which
 * would have gone out on posts looking like a mistake. Only something that
 * actually looks at the picture can tell a photo of a desk from a
 * screenshot of a web page, so Claude does, once per photo, at thumbnail
 * size. The description it writes also lets a post pick a photo that
 * matches what the post is about (see ImagePicker).
 */
class ImageVetter
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    // What vetting found, as stored in business_images.vetting_note when a
    // photo is switched off. The Photos page turns these into sentences.
    public const NOTE_SCREENSHOT = 'screenshot';
    public const NOTE_TEXT = 'mostly_text';
    public const NOTE_LOGO = 'logo';
    public const NOTE_UNSUITABLE = 'unsuitable';
    public const NOTE_DUPLICATE = 'duplicate';

    private const PROMPT = <<<'PROMPT'
This image is from a small UK business's own website or Google Business Profile. We are deciding whether it can be used as the picture on one of that business's social media posts.

Classify it:
- "photo": a real photograph (of anything: premises, work, products, people, places, pets).
- "illustration": a drawing, cartoon or rendered artwork that is not mainly text.
- "screenshot": a capture of a website, app, software, document or social media post.
- "text_graphic": an image that is mainly words, such as a banner, slide, quote card, price list or poster.
- "logo": a logo, badge, icon or wordmark on its own.
- "other": anything else.

It is usable only if it is a photo or illustration, is not mainly text, would not embarrass the business on its own page, and does not mainly show another company's branding or website.

Reply with only this JSON, no other text:
{"kind": "photo|illustration|screenshot|text_graphic|logo|other", "usable": true or false, "description": "one plain sentence, under 25 words, saying what the image shows, with no opinions about quality"}
PROMPT;

    /**
     * @return array{kind: string, usable: bool, description: string, note: string|null}
     *
     * @throws \RuntimeException When the API cannot be reached or answers badly; the photo is left unvetted and tried again later.
     */
    public function vet(string $jpegBytes): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => config('services.anthropic.version'),
        ])
            ->timeout(30)
            ->retry(2, 2000, throw: false)
            ->post(config('services.anthropic.base_url').'/messages', [
                'model' => self::MODEL,
                'max_tokens' => 200,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'image/jpeg',
                                'data' => base64_encode($jpegBytes),
                            ],
                        ],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Image vetting failed with HTTP '.$response->status());
        }

        $verdict = self::parse((string) ($response->json('content.0.text') ?? ''));

        if ($verdict === null) {
            throw new \RuntimeException('Image vetting returned no usable answer');
        }

        return $verdict;
    }

    /**
     * Turn the model's reply into a verdict, or null if it cannot be read.
     *
     * Deliberately strict about what counts as usable: the kind must be one we
     * post, whatever the model said about usable, so a confused answer can
     * only ever switch a photo off, never put a screenshot on a post.
     *
     * @return array{kind: string, usable: bool, description: string, note: string|null}|null
     */
    public static function parse(string $reply): ?array
    {
        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($reply, $start, $end - $start + 1), true);

        if (! is_array($data) || ! isset($data['kind'])) {
            return null;
        }

        $kind = strtolower(trim((string) $data['kind']));
        $known = ['photo', 'illustration', 'screenshot', 'text_graphic', 'logo', 'other'];
        $kind = in_array($kind, $known, true) ? $kind : 'other';

        $usable = ($data['usable'] ?? false) === true && in_array($kind, BusinessImage::USABLE_KINDS, true);

        $note = null;
        if (! $usable) {
            $note = match ($kind) {
                'screenshot' => self::NOTE_SCREENSHOT,
                'text_graphic' => self::NOTE_TEXT,
                'logo' => self::NOTE_LOGO,
                default => self::NOTE_UNSUITABLE,
            };
        }

        $description = trim(preg_replace('/\s+/u', ' ', (string) ($data['description'] ?? '')) ?? '');

        return [
            'kind' => $kind,
            'usable' => $usable,
            'description' => mb_substr($description, 0, 300),
            'note' => $note,
        ];
    }
}
