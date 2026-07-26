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
 * Privacy API implementation for the certificatebeautiful plugin.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certificatebeautiful\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use Exception;
use moodle_recordset;

/**
 * Privacy API implementation for the certificatebeautiful plugin.
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table("certificatebeautiful_issue", [
            "userid" => 'privacy:metadata:certificatebeautiful_issue:userid',
        ], 'privacy:metadata:certificatebeautiful_issue');

        $collection->add_database_table("certificatebeautiful_usersignature", [
            "userid" => 'privacy:metadata:usersignature:userid',
            "font_style" => 'privacy:metadata:usersignature:font_style',
            "signature_text" => 'privacy:metadata:usersignature:signature_text',
            "timecreated" => 'privacy:metadata:usersignature:timecreated',
            "timemodified" => 'privacy:metadata:usersignature:timemodified',
        ], 'privacy:metadata:usersignature');

        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:filearea:signature'
        );

        return $collection;
    }

    /**
     * Function get_contexts_for_userid
     *
     * @param int $userid
     * @return contextlist
     * @throws Exception
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        if (!\core_user::get_user($userid)) {
            return new contextlist();
        }

        $sql = "SELECT c.id
                  FROM {context}                    c
            INNER JOIN {course_modules}             cm  ON cm.id  = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules}                    m   ON m.id   = cm.module     AND m.name = :modname
            INNER JOIN {certificatebeautiful_issue} i   ON i.cmid = cm.id
                 WHERE i.userid = :userid";

        $params = [
            "modname" => "certificatebeautiful",
            "contextlevel" => CONTEXT_MODULE,
            "userid" => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {certificatebeautiful_usersignature} sig
                  JOIN {context} ctx
                    ON ctx.instanceid = sig.userid
                   AND ctx.contextlevel = :ctxlevel
                 WHERE sig.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Function export_user_data
     *
     * @param approved_contextlist $contextlist
     * @throws Exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $cmids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);

        $user = $contextlist->get_user();

        if (!empty($cmids)) {
            $instanceidstocmids = self::get_instance_ids_to_cmids_from_cmids($cmids);
            $instanceids = array_keys($instanceidstocmids);

            list($insql, $inparams) = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $params = array_merge($inparams, ["userid" => $user->id]);
            $recordset = $DB->get_recordset_select(
                "certificatebeautiful_issue",
                "cmid $insql AND userid = :userid",
                $params,
                'timecreated, id'
            );
            self::recordset_loop_and_export($recordset, "cmid", [],
                function ($carry, $record) use ($user, $instanceidstocmids) {
                    $carry[] = [
                        "timecreated" => userdate($record->timecreated),
                        "code" => $record->code,
                    ];
                    return $carry;
                },
                function ($instanceid, $data) use ($user, $instanceidstocmids) {
                    $context = context_module::instance($instanceidstocmids[$instanceid]);
                    $contextdata = helper::get_context_data($context, $user);
                    $finaldata = (object)array_merge((array)$contextdata, ["logs" => $data]);
                    helper::export_context_files($context, $user);
                    writer::with_context($context)->export_data([], $finaldata);
                }
            );
        }

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || $context->instanceid != $user->id) {
                continue;
            }

            $record = $DB->get_record('certificatebeautiful_usersignature', ['userid' => $user->id]);
            if (!$record) {
                continue;
            }

            $data = (object) [
                'font_style'     => $record->font_style,
                'signature_text' => $record->signature_text,
                'timecreated'    => transform::datetime($record->timecreated),
                'timemodified'   => transform::datetime($record->timemodified),
            ];

            $subcontext = [get_string('mysignature', 'certificatebeautiful')];
            writer::with_context($context)->export_data($subcontext, $data);
            writer::with_context($context)->export_area_files(
                $subcontext, 'mod_certificatebeautiful', 'signature', 0
            );
        }
    }

    /**
     * Function delete_data_for_all_users_in_context
     *
     * @param context $context
     *
     * @throws Exception
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $instanceid = $DB->get_field("course_modules", "instance", ["id" => $context->instanceid], MUST_EXIST);
        $DB->delete_records("certificatebeautiful_issue", ["cmid" => $instanceid]);
    }

    /**
     * Function delete_data_for_user
     *
     * @param approved_contextlist $contextlist
     * @throws Exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $count = $contextlist->count();
        if (empty($count)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_module) {
                $instanceid = $DB->get_field("course_modules", "instance", ["id" => $context->instanceid], MUST_EXIST);
                $DB->delete_records("certificatebeautiful_issue", ["cmid" => $instanceid, "userid" => $userid]);
            }
            if ($context instanceof \context_user && $context->instanceid == $userid) {
                $DB->delete_records("certificatebeautiful_usersignature", ["userid" => $userid]);
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_certificatebeautiful', 'signature');
            }
        }
    }

    /**
     * Function get_instance_ids_to_cmids_from_cmids
     *
     * @param array $cmids
     * @return array
     * @throws Exception
     */
    protected static function get_instance_ids_to_cmids_from_cmids(array $cmids) {
        global $DB;

        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "SELECT cb.id, cm.id AS cmid
                 FROM {certificatebeautiful} cb
                 JOIN {modules}               m ON m.name = 'certificatebeautiful'
                 JOIN {course_modules}       cm ON cm.instance = cb.id AND cm.module   = m.id
                WHERE cm.id $insql";
        $params = array_merge($inparams, []);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Function recordset_loop_and_export
     *
     * @param moodle_recordset $recordset
     * @param string $splitkey
     * @param object $initial
     * @param callable $reducer
     * @param callable $export
     */
    protected static function recordset_loop_and_export(
        moodle_recordset $recordset,
        $splitkey,
        $initial,
        callable $reducer,
        callable $export
    ) {
        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }

    /**
     * Function get_users_in_context
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context instanceof context_module) {
            $params = [
                "instanceid" => $context->instanceid,
            ];
            $sql = "SELECT i.userid
                      FROM {course_modules}             cm
                      JOIN {certificatebeautiful_issue} i  ON i.cmid = cm.id
                     WHERE cm.id = :instanceid";
            $userlist->add_from_sql("userid", $sql, $params);
        }

        if ($context instanceof \context_user) {
            if (self::user_has_signature($context->instanceid)) {
                $userlist->add_user($context->instanceid);
            }
        }
    }

    /**
     * Function delete_data_for_users
     *
     * @param approved_userlist $userlist
     * @throws Exception
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof context_module) {
            $cm = $DB->get_record("course_modules", ["id" => $context->instanceid]);

            list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $params = array_merge(["cmid" => $cm->instance], $userinparams);
            $sql = "cmid = :cmid AND userid {$userinsql}";

            $DB->delete_records_select("certificatebeautiful_issue", $sql, $params);
        }

        if ($context instanceof \context_user) {
            foreach ($userlist->get_userids() as $userid) {
                if ($userid != $context->instanceid) {
                    continue;
                }
                $DB->delete_records('certificatebeautiful_usersignature', ['userid' => $userid]);
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_certificatebeautiful', 'signature');
            }
        }
    }

    private static function user_has_signature(int $userid): bool {
        global $DB;
        return $DB->record_exists('certificatebeautiful_usersignature', ['userid' => $userid]);
    }
}
