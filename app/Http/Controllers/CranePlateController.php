<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class CranePlateController extends Controller
{
    /**
     * Returns the official plate template with only the crane number replaced.
     * The plate font is embedded so the number renders correctly inside the image.
     */
    public function __invoke(string $code): Response
    {
        $template = file_get_contents(public_path('images/plate-template.svg'));
        $fontData = base64_encode(file_get_contents(public_path('fonts/fe-font.ttf')));

        $fontFace = '@font-face{font-family:"FE-Font";src:url(data:font/ttf;base64,'.$fontData.') format("truetype");}';

        $svg = str_replace('<style>', '<style>'.$fontFace, $template);
        $svg = str_replace(
            '<tspan x="-14.772" y="0">4778</tspan>',
            '<tspan x="-14.772" y="0">'.htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</tspan>',
            $svg,
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
