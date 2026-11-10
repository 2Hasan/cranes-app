<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class CranePlateController extends Controller
{
    /**
     * Returns the official plate template with only the crane number replaced.
     */
    public function __invoke(string $code): Response
    {
        $template = file_get_contents(public_path('images/plate-template.svg'));

        $svg = str_replace(
            '<tspan x="-14.772" y="0">4778</tspan>',
            '<tspan x="-14.772" y="0">'.htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</tspan>',
            $template,
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
