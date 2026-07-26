<?php
namespace certificatebeautifuldatainfo_usersignature\datainfo;

defined('MOODLE_INTERNAL') || die();

use mod_certificatebeautiful\datainfo\help_base;

class usersignature extends help_base {

    const CLASS_NAME = 'usersignature';

    public static function table_structure(): array {
        return [
            ['key' => 'signature_img',  'label' => get_string('tag_signature_img',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_url',  'label' => get_string('tag_signature_url',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_has',  'label' => get_string('tag_signature_has',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'signature_font', 'label' => get_string('tag_signature_font', 'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_img1',  'label' => get_string('tag_teacher_signature_img1',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_img2',  'label' => get_string('tag_teacher_signature_img2',  'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_all',   'label' => get_string('tag_teacher_signature_all',   'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_name1', 'label' => get_string('tag_teacher_signature_name1', 'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'teacher_signature_name2', 'label' => get_string('tag_teacher_signature_name2', 'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'course_startdate_br',     'label' => get_string('tag_course_startdate_br',     'certificatebeautifuldatainfo_usersignature')],
            ['key' => 'course_enddate_br',       'label' => get_string('tag_course_enddate_br',       'certificatebeautifuldatainfo_usersignature')],
        ];
    }

    public static function get_data($course, $user): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/certificatebeautiful/lib.php');

        $userid = (int) $user->id;

        $datauri = certificatebeautiful_get_signature_datauri($userid);
        $has     = ($datauri !== '');

        $url        = certificatebeautiful_get_signature_url($userid);
        $urlstring = ($url !== null) ? $url->out() : '';

        $meta = certificatebeautiful_get_signature_meta($userid);

        $imgtag = '';
        if ($has) {
            $imgtag = self::build_img(
                $datauri,
                get_string('mysignature', 'certificatebeautiful') . ' — ' . fullname($user)
            );
        }

        $teachers = self::get_course_teachers($course);
        $t1 = $teachers[0] ?? null;
        $t2 = $teachers[1] ?? null;

        $course_startdate_br = '';
        if (!empty($course->startdate)) {
            $course_startdate_br = userdate($course->startdate, '%d/%m/%Y');
        }
        $course_enddate_br = '';
        if (!empty($course->enddate)) {
            $course_enddate_br = userdate($course->enddate, '%d/%m/%Y');
        }

        return [
            'signature_img'  => $imgtag,
            'signature_url'  => $urlstring,
            'signature_has'  => $has ? '1' : '0',
            'signature_font' => $meta['font'] ?? '',

            'teacher_signature_img1'  => $t1 ? self::teacher_img($t1) : '',
            'teacher_signature_img2'  => $t2 ? self::teacher_img($t2) : '',
            'teacher_signature_all'   => self::teachers_block($teachers),
            'teacher_signature_name1' => $t1 ? fullname($t1) : '',
            'teacher_signature_name2' => $t2 ? fullname($t2) : '',

            'course_startdate_br'     => $course_startdate_br,
            'course_enddate_br'       => $course_enddate_br,
        ];
    }

    public static function process_html(string $html, $course, $user): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/certificatebeautiful/lib.php');

        $datauri = certificatebeautiful_get_signature_datauri((int) $user->id);
        if ($datauri === '') {
            $html = preg_replace(
                '/<[^>]+data-sig-required=["\']true["\'][^>]*>.*?<\/\w+>/si',
                '',
                $html
            );
        }
        return $html;
    }

    private static function build_img(string $datauri, string $alt): string {
        return sprintf(
            '<img src="%s" alt="%s" style="max-height:60px;width:auto;display:block;margin:0 auto;">',
            $datauri,
            htmlspecialchars($alt, ENT_QUOTES)
        );
    }

    private static function teacher_img($teacher): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/certificatebeautiful/lib.php');

        $datauri = certificatebeautiful_get_signature_datauri((int) $teacher->id);
        if ($datauri === '') {
            return '';
        }
        return self::build_img($datauri, fullname($teacher));
    }

    private static function teachers_block(array $teachers): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/certificatebeautiful/lib.php');

        $blocks = [];
        foreach ($teachers as $teacher) {
            $datauri = certificatebeautiful_get_signature_datauri((int) $teacher->id);
            if ($datauri === '') {
                continue;
            }
            $blocks[] = sprintf(
                '<span style="display:inline-block;margin:0 18px;text-align:center;vertical-align:bottom;">'
                . '%s<span style="display:block;font-size:11px;margin-top:2px;">%s</span></span>',
                self::build_img($datauri, fullname($teacher)),
                htmlspecialchars(fullname($teacher), ENT_QUOTES)
            );
        }
        return implode('', $blocks);
    }

    private static function get_course_teachers($course): array {
        global $CFG;

        if (empty($course->id)) {
            return [];
        }
        $context = \context_course::instance($course->id, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        if (!empty($CFG->coursecontact)) {
            $roleids = explode(',', $CFG->coursecontact);
        } else {
            list($roleids) = get_roles_with_cap_in_context($context, 'moodle/course:manage');
        }

        $teachers = [];
        foreach ($roleids as $roleid) {
            foreach (get_role_users((int) $roleid, $context, true) as $u) {
                if (!isset($teachers[$u->id])) {
                    $teachers[$u->id] = $u;
                }
            }
        }
        return array_values($teachers);
    }
}
