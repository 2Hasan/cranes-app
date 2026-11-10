<?php

namespace App\Http\Controllers;

use App\Services\FontOutline;
use Illuminate\Http\Response;

class CranePlateController extends Controller
{
    /**
     * Returns the official plate template with only the crane number replaced.
     * The number is drawn as outlines from the plate font, so it looks the same everywhere.
     */
    public function __invoke(string $code): Response
    {
        $template = (string) file_get_contents(public_path('images/plate-template.svg'));

        $numberPath = (new FontOutline(public_path('fonts/fe-font.ttf')))->path($code, -14.772, 12);

        $svg = preg_replace(
            '/<text transform="matrix\(8\.036[^"]*"[^>]*class="cls-6">.*?<\/text>/s',
            '<path fill="#000000" transform="matrix(8.036 0 0 8.036 196 158.022)" d="'.$numberPath.'"/>',
            $template,
            1,
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
