<?php

namespace Tests\Unit;

use App\Modules\Content\Services\HashtagGuard;
use PHPUnit\Framework\TestCase;

class HashtagGuardTest extends TestCase
{
    private function guard(): HashtagGuard
    {
        return new HashtagGuard([
            'Smith Plumbing',
            'Plumbing and heating',
            'Mansfield, NG18, UK',
            'Boiler servicing, bathroom fitting and emergency repairs across Mansfield.',
        ]);
    }

    public function test_keeps_tags_built_from_business_context(): void
    {
        $result = $this->guard()->clean("Boiler playing up?\n\n#MansfieldPlumber #BoilerServicing", 2);

        $this->assertSame(['#MansfieldPlumber', '#BoilerServicing'], $result['hashtags']);
        $this->assertSame([], $result['removed']);
    }

    public function test_drops_a_town_the_business_is_not_in(): void
    {
        $result = $this->guard()->clean("Boiler playing up?\n\n#NottinghamPlumber #ShopLocal", 2);

        $this->assertSame(['#ShopLocal'], $result['hashtags']);
        $this->assertSame(['#NottinghamPlumber'], $result['removed']);
        $this->assertSame("Boiler playing up?\n\n#ShopLocal", $result['content']);
    }

    public function test_drops_an_unrelated_trade(): void
    {
        $result = $this->guard()->clean("Fixed today.\n#HairSalon", 2);

        $this->assertSame([], $result['hashtags']);
        $this->assertSame('Fixed today.', $result['content']);
    }

    public function test_enforces_the_platform_limit_and_removes_repeats(): void
    {
        $result = $this->guard()->clean("Done.\n#Plumbing #plumbing #Heating #Mansfield", 2);

        $this->assertSame(['#Plumbing', '#Heating'], $result['hashtags']);
        $this->assertSame("Done.\n#Plumbing #Heating", $result['content']);
    }

    public function test_zero_limit_strips_every_tag(): void
    {
        $result = $this->guard()->clean("Open as usual.\n\n#Plumbing", 0);

        $this->assertSame('Open as usual.', $result['content']);
    }

    public function test_inline_tag_loses_only_its_hash(): void
    {
        $result = $this->guard()->clean('Proud to serve #Nottingham and beyond.', 2);

        $this->assertSame('Proud to serve Nottingham and beyond.', $result['content']);
    }

    public function test_ignores_urls_and_html_entities(): void
    {
        $content = 'See https://example.com/page#reviews for more.';
        $result = $this->guard()->clean($content, 2);

        $this->assertSame($content, $result['content']);
    }
}
