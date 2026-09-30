<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class certificateissue
 *
 * @package   certificatebeautifuldatainfo_certificateissue
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace certificatebeautifuldatainfo_certificateissue\datainfo;

use Exception;
use mod_certificatebeautiful\datainfo\help_base;
use certificatebeautifuldatainfo_certificateissue\util\qrcode;
use mod_certificatebeautiful\vo\certificatebeautiful;
use mod_certificatebeautiful\vo\certificatebeautiful_issue;

/**
 * Class certificateissue
 *
 * @package certificatebeautifuldatainfo_certificateissue
 */
class certificateissue extends help_base {

    /**
     * CLASS_NAME value
     */
    const CLASS_NAME = "certificate";

    /**
     * Function table_structure
     *
     * @return array
     * @throws Exception
     */
    public static function table_structure() {
        return [
            ["key" => "name", "label" => get_string("name", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "description", "label" => get_string("description", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "timecreated", "label" => get_string("timecreated", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "code", "label" => get_string("code", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "codelink", "label" => get_string("codelink", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "url", "label" => get_string("url", "certificatebeautifuldatainfo_certificateissue")],
            ["key" => "page", "label" => get_string("page", "certificatebeautifuldatainfo_certificateissue")],
        ];
    }

    /**
     * Function get_data
     *
     * @param certificatebeautiful $certificatebeautiful
     * @param certificatebeautiful_issue $issue
     * @return array
     * @throws Exception
     */
    public static function get_data($certificatebeautiful, $issue) {
        global $CFG;

        // Work on a copy of the issue record. The caller keeps using the original
        // object (e.g. to update certificatebeautiful_issue after generating the
        // PDF); mutating timecreated to a formatted date here would be written
        // back to the database and break the update with a bigint error.
        $issuedata = clone $issue;

        $issuedata->description = str_replace("\n", "<br>", trim($certificatebeautiful->description));
        $issuedata->url = "{$CFG->wwwroot}/mod/certificatebeautiful/v/?code={$issuedata->code}";
        $issuedata->page = "{$CFG->wwwroot}/mod/certificatebeautiful/v/";
        $issuedata->codelink = "<a href=\"{$issuedata->url}\">{$issuedata->code}</a>";

        $issuedata->timecreated = userdate($issuedata->timecreated);

        return self::base_get_data(self::table_structure(), $issuedata);
    }

    /**
     * Function custom_replace
     *
     * @param string $html
     * @param array $issuedata
     * @return string
     */
    public static function custom_replace($html, $issuedata) {
        if (strpos($html, "img/qr-code.svg")) {

            $pngfile = make_temp_directory("certificatebeautiful") . "/" . uniqid() . ".png";

            $options = [
                "wq" => 0,
                "w" => 500,
                "h" => 500,
                "p" => 0,
            ];
            $generator = new qrcode($issuedata["url"], $options);
            $image = $generator->render_image();
            imagepng($image, $pngfile);

            $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($pngfile));
            $html = preg_replace('/(src=["\'])[^"\']+img\/qr-code.svg(["\'])/i', '$1' . $base64 . '$2', $html);
        }

        return $html;
    }
}
