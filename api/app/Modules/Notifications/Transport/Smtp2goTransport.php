<?php

namespace App\Modules\Notifications\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through SMTP2GO's v3 HTTP API.
 *
 * We use the API rather than SMTP because the credential we hold is an API key
 * ("api-...") — SMTP2GO's SMTP servers authenticate against a separate SMTP user
 * username/password pair, and the two are not interchangeable. Using the API also
 * gives us a per-message id and a structured rejection reason in the response,
 * which SMTP does not surface.
 */
class Smtp2goTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.smtp2go.com/v3/email/send',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = array_filter([
            'sender'    => $this->formatAddress($email->getFrom()[0] ?? null),
            'to'        => $this->formatAddresses($email->getTo()),
            'cc'        => $this->formatAddresses($email->getCc()),
            'bcc'       => $this->formatAddresses($email->getBcc()),
            'subject'   => $email->getSubject(),
            'html_body' => $email->getHtmlBody(),
            'text_body' => $email->getTextBody(),
            'custom_headers' => $this->customHeaders($email),
            'attachments'    => $this->attachments($email),
        ], fn ($value) => $value !== null && $value !== []);

        $replyTo = $this->formatAddresses($email->getReplyTo());
        if ($replyTo !== []) {
            // The API takes a single reply-to string, not a list.
            $payload['reply_to'] = $replyTo[0];
        }

        $response = Http::asJson()
            ->withHeaders(['X-Smtp2go-Api-Key' => $this->apiKey])
            ->timeout(30)
            ->post($this->endpoint, $payload);

        $body = $response->json() ?? [];

        // SMTP2GO answers 200 with an error object rather than an HTTP error code
        // for things like an unverified sender, so status alone is not enough.
        if ($response->failed() || isset($body['data']['error'])) {
            throw new \RuntimeException(
                'SMTP2GO rejected the message: '
                .($body['data']['error'] ?? $response->body())
            );
        }

        $succeeded = $body['data']['succeeded'] ?? 0;
        if ($succeeded < 1) {
            $failures = $body['data']['failures'] ?? [];

            throw new \RuntimeException(
                'SMTP2GO accepted the request but delivered to nobody: '
                .(empty($failures) ? 'no failure detail returned' : json_encode($failures))
            );
        }
    }

    /**
     * @param  Address[]  $addresses
     * @return string[]
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_filter(array_map(
            fn (Address $address) => $this->formatAddress($address),
            $addresses
        )));
    }

    private function formatAddress(?Address $address): ?string
    {
        if (! $address) {
            return null;
        }

        $name = trim($address->getName());

        return $name === ''
            ? $address->getAddress()
            : sprintf('"%s" <%s>', str_replace('"', "'", $name), $address->getAddress());
    }

    /**
     * Pass through anything the app set that the API does not model directly.
     *
     * @return array<int, array{header: string, value: string}>
     */
    private function customHeaders(Email $email): array
    {
        $skip = ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'mime-version', 'date', 'message-id'];
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            if (in_array(strtolower($header->getName()), $skip, true)) {
                continue;
            }

            $headers[] = [
                'header' => $header->getName(),
                'value'  => $header->getBodyAsString(),
            ];
        }

        return $headers;
    }

    /**
     * @return array<int, array{filename: string, fileblob: string, mimetype: string}>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $attachments[] = [
                'filename' => $headers->getHeaderParameter('content-disposition', 'filename') ?: 'attachment',
                'fileblob' => base64_encode($attachment->getBody()),
                'mimetype' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            ];
        }

        return $attachments;
    }

    public function __toString(): string
    {
        return 'smtp2go';
    }
}
