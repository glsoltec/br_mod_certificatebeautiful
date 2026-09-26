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
 * Access helpers for certificate issues.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2026 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certificatebeautiful;

use context_module;
use stdClass;

/**
 * Centralizes authorization rules for issued certificates.
 */
class access_manager {

    /**
     * Checks whether a user can view an issued certificate.
     *
     * Owners need the normal view capability. Other users need full report access
     * (viewreport) or, when they only have group access, must share a course group
     * with the certificate owner.
     *
     * @param stdClass $issue Certificate issue record.
     * @param context_module $context Module context.
     * @param int $userid User id.
     * @return bool
     */
    public static function can_view_issue(stdClass $issue, context_module $context, int $userid): bool {
        if ((int)$issue->userid === $userid) {
            return has_capability("mod/certificatebeautiful:view", $context, $userid);
        }

        if (has_capability("mod/certificatebeautiful:viewreport", $context, $userid)) {
            return true;
        }

        if (has_capability("mod/certificatebeautiful:viewgroupcertificates", $context, $userid)) {
            return !empty(self::shared_groups($context, $userid, (int)$issue->userid));
        }

        return false;
    }

    /**
     * Requires permission to view an issued certificate.
     *
     * @param stdClass $issue Certificate issue record.
     * @param context_module $context Module context.
     * @return void
     * @throws \moodle_exception When group access is not enough to see the certificate.
     */
    public static function require_view_issue(stdClass $issue, context_module $context): void {
        global $USER;

        if ((int)$issue->userid === (int)$USER->id) {
            require_capability("mod/certificatebeautiful:view", $context);
            return;
        }

        if (has_capability("mod/certificatebeautiful:viewreport", $context)) {
            return;
        }

        if (has_capability("mod/certificatebeautiful:viewgroupcertificates", $context)) {
            if (!empty(self::shared_groups($context, (int)$USER->id, (int)$issue->userid))) {
                return;
            }
            throw new \moodle_exception("notsamegroup", "certificatebeautiful");
        }

        require_capability("mod/certificatebeautiful:viewreport", $context);
    }

    /**
     * Requires permission to create or delete issues for other users.
     *
     * @param context_module $context Module context.
     * @return void
     */
    public static function require_manage_issues(context_module $context): void {
        require_capability("mod/certificatebeautiful:viewreport", $context);
    }

    /**
     * Checks whether the user can access the certificates report of other users.
     *
     * @param context_module $context Module context.
     * @param int $userid User id.
     * @return bool
     */
    public static function can_report(context_module $context, int $userid): bool {
        if (has_capability("mod/certificatebeautiful:viewreport", $context, $userid)) {
            return true;
        }
        return has_capability("mod/certificatebeautiful:viewgroupcertificates", $context, $userid);
    }

    /**
     * Checks whether the user is limited to certificates of users in their own groups.
     *
     * @param context_module $context Module context.
     * @param int $userid User id.
     * @return bool
     */
    public static function is_group_limited(context_module $context, int $userid): bool {
        return !has_capability("mod/certificatebeautiful:viewreport", $context, $userid)
            && has_capability("mod/certificatebeautiful:viewgroupcertificates", $context, $userid);
    }

    /**
     * Returns the course id of a module context.
     *
     * @param context_module $context Module context.
     * @return int
     */
    public static function course_id(context_module $context): int {
        $coursecontext = $context->get_course_context(false);
        return $coursecontext ? (int)$coursecontext->instanceid : 0;
    }

    /**
     * Returns the ids of the course groups shared by two users.
     *
     * @param context_module $context Module context.
     * @param int $userid1 First user id.
     * @param int $userid2 Second user id.
     * @return int[] Shared group ids.
     */
    public static function shared_groups(context_module $context, int $userid1, int $userid2): array {
        if ($userid1 === $userid2) {
            return [];
        }

        $courseid = self::course_id($context);
        $groups1 = array_keys(groups_get_all_groups($courseid, $userid1, 0, "g.id"));
        if (!$groups1) {
            return [];
        }
        $groups2 = array_keys(groups_get_all_groups($courseid, $userid2, 0, "g.id"));
        if (!$groups2) {
            return [];
        }

        return array_values(array_intersect($groups1, $groups2));
    }
}
