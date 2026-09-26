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
 * Access manager tests.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2026 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certificatebeautiful;

use advanced_testcase;
use context_module;

/**
 * Tests issue access rules.
 *
 * @covers \mod_certificatebeautiful\access_manager
 */
final class access_manager_test extends advanced_testcase {

    /**
     * Owners can view their own issue, other students cannot, and report users can view others.
     */
    public function test_issue_access_uses_owner_or_viewreport(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $generator->create_user();
        $otherstudent = $generator->create_user();
        $teacher = $generator->create_user();

        $generator->enrol_user($owner->id, $course->id, "student");
        $generator->enrol_user($otherstudent->id, $course->id, "student");
        $generator->enrol_user($teacher->id, $course->id, "teacher");

        $modelid = $this->get_model_id();
        $activity = $generator->create_module("certificatebeautiful", [
            "course" => $course->id,
            "name" => "Access certificate",
            "description" => "Test certificate",
            "model" => $modelid,
            "autotrigger" => "",
            "triggercmid" => 0,
            "gradepass" => "",
            "notifyuser" => 0,
        ]);
        $cm = get_coursemodule_from_instance("certificatebeautiful", $activity->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $issue = (object)["userid" => $owner->id];

        $this->assertTrue(access_manager::can_view_issue($issue, $context, $owner->id));
        $this->assertFalse(access_manager::can_view_issue($issue, $context, $otherstudent->id));
        $this->assertTrue(access_manager::can_view_issue($issue, $context, $teacher->id));
    }

    /**
     * Group-limited users can only view issues of users in their own groups.
     */
    public function test_issue_access_group_limited(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $tutor = $generator->create_user();
        $samegroup = $generator->create_user();
        $othergroup = $generator->create_user();

        $group1 = $generator->create_group(["courseid" => $course->id, "name" => "G1"]);
        $group2 = $generator->create_group(["courseid" => $course->id, "name" => "G2"]);
        $generator->create_group_member(["groupid" => $group1->id, "userid" => $tutor->id]);
        $generator->create_group_member(["groupid" => $group1->id, "userid" => $samegroup->id]);
        $generator->create_group_member(["groupid" => $group2->id, "userid" => $othergroup->id]);

        $modelid = $this->get_model_id();
        $activity = $generator->create_module("certificatebeautiful", [
            "course" => $course->id,
            "name" => "Group certificate",
            "description" => "Test certificate",
            "model" => $modelid,
            "autotrigger" => "",
            "triggercmid" => 0,
            "gradepass" => "",
            "notifyuser" => 0,
        ]);
        $cm = get_coursemodule_from_instance("certificatebeautiful", $activity->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Role with only group-level access to certificates.
        $roleid = $generator->create_role();
        assign_capability("mod/certificatebeautiful:view", CAP_ALLOW, $roleid, $context->id, true);
        assign_capability("mod/certificatebeautiful:viewgroupcertificates", CAP_ALLOW, $roleid, $context->id, true);
        assign_capability("mod/certificatebeautiful:viewreport", CAP_PREVENT, $roleid, $context->id, true);
        role_assign($roleid, $tutor->id, $context->id);

        $sameissue = (object)["userid" => $samegroup->id];
        $otherissue = (object)["userid" => $othergroup->id];

        $this->assertTrue(access_manager::is_group_limited($context, $tutor->id));
        $this->assertTrue(access_manager::can_report($context, $tutor->id));
        $this->assertTrue(access_manager::can_view_issue($sameissue, $context, $tutor->id));
        $this->assertFalse(access_manager::can_view_issue($otherissue, $context, $tutor->id));
    }

    /**
     * Returns an existing model or creates a minimal model for the test.
     *
     * @return int
     */
    private function get_model_id(): int {
        global $DB;

        $modelid = (int)$DB->get_field_sql("SELECT MIN(id) FROM {certificatebeautiful_model}");
        if ($modelid) {
            return $modelid;
        }

        return $DB->insert_record("certificatebeautiful_model", (object)[
            "name" => "Test model",
            "orientation" => "L",
            "model_key" => "test-model",
            "pages_info" => "[]",
            "timecreated" => time(),
            "timemodified" => time(),
        ]);
    }
}
