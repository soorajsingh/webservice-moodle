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
 * This plugin provides access to Moodle data in form of analytics and reports in real time.
 *
 * @package    local_intelliboard
 * @copyright  2017 IntelliBoard, Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @website    http://intelliboard.net/
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class location_webservices extends external_api {

    /**
     * Parameters For Create Location (grouping)
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
	
	public static function create_location_parameters() {  
        return new external_function_parameters(
			array(
				'idnumber'   => new external_value(PARAM_INT, 'Location Idnumber', VALUE_REQUIRED),
				'name'       => new external_value(PARAM_TEXT, 'Location Name',    VALUE_REQUIRED)
			)
        );
    }

    /**
     * 
     *
     * Accept parameter and perform require action.
     * @return array An array of arrays
     * @since Moodle 2.5
     */
    public static function create_location($idnumber, $name) {
        global $CFG, $DB;

        $params = self::validate_parameters(
						self::create_location_parameters(), 
						array(
							'idnumber'   => $idnumber,
							'name'       => $name
						)
					);
		
		
        self::validate_context(context_system::instance());

        $params      = (object)$params;
		$transaction = $DB->start_delegated_transaction();
	
		$select      = "id <> 1";
		$all_course  = $DB->get_records_select('course', $select);
		
		foreach($all_course as $course) {
				
				$grouping_record               = new stdClass();
				$grouping_record->idnumber     = $params->idnumber;
				$grouping_record->name         = $params->name;
				$grouping_record->courseid     = $course->id;
				$grouping_record->timecreated  = time();
				$grouping_record->timemodified = time();
				$DB->insert_record('groupings', $grouping_record);
		}

        $transaction->allow_commit();

        return $params;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
	
	public static function create_location_returns() {
       return new external_single_structure(
            array(
                    'idnumber'   => new external_value(PARAM_INT, 'Location idnumber'),
                    'name'       => new external_value(PARAM_TEXT, 'Location Name')
                )
        );
    }
	
	
	
	/**
     * Parameters For Create Variant (group)
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
	
	public static function create_variant_parameters() {
        return new external_function_parameters(
			array(
				'group_idnumber'    => new external_value(PARAM_INT,  'Group Idnumber', VALUE_REQUIRED),
				'name'              => new external_value(PARAM_TEXT, 'Group Name', VALUE_REQUIRED),
				'course_idnumber'   => new external_value(PARAM_RAW,  'Course Idnumber', VALUE_REQUIRED),
				'location_idnumber' => new external_value(PARAM_INT,  'Location Idnumber', VALUE_REQUIRED)
			)
        );
    }

    /**
     * 
     *
     * Accept parameter and perform require action.
     * @return array An array of arrays
     * @since Moodle 2.5
     */
    public static function create_variant($group_idnumber, $name, $course_idnumber, $location_idnumber) {
        global $CFG, $DB;

        $params = self::validate_parameters(
						self::create_variant_parameters(), 
						array(
							'group_idnumber'    => $group_idnumber, 
							'name'              => $name, 
							'course_idnumber'   => $course_idnumber, 
							'location_idnumber' => $location_idnumber
						)
					);
		
		$params      = (object)$params;
        $transaction = $DB->start_delegated_transaction();

        self::validate_context(context_system::instance());
		
		//Create Group
		
		$select     = 'idnumber = "'.$params->course_idnumber .'"';
		$courseid = $DB->get_record_select('course', $select, null , $fields='id', $strictness=IGNORE_MISSING);
		
		$select     = 'courseid = '.$courseid->id.' and idnumber = '.$params->location_idnumber .'';
		$groupingid = $DB->get_record_select('groupings', $select, null , $fields='*', $strictness=IGNORE_MISSING);
		
		$group_record                    = new stdClass();
		$group_record->idnumber          = $params->group_idnumber;
		$group_record->courseid          = $courseid->id;
		$group_record->name              = $params->name.'--'.$groupingid->name;
		$group_record->enrolmentkey      = '';
		$group_record->description       = '';
		$group_record->descriptionformat = 1;
		$group_record->timecreated       = time();
		$group_record->timemodified      = time();
		
		$groupid = $DB->insert_record('groups', $group_record);
		
		//Map Group with Grouping
		
		
		
		$gp_mapping             = new stdClass();
		$gp_mapping->groupingid = $groupingid->id;
		$gp_mapping->groupid    = $groupid;
		$gp_mapping->timeadded  = time();
		$DB->insert_record('groupings_groups', $gp_mapping);
		

        $transaction->allow_commit();
		
		purge_all_caches();

        return $params;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
	
	public static function create_variant_returns() {
       return new external_single_structure(
            array(
                    'group_idnumber'    => new external_value(PARAM_INT,  'Group Idnumber'),
                    'name'              => new external_value(PARAM_TEXT, 'Group Name'),
                    'course_idnumber'   => new external_value(PARAM_RAW, 'Course Idnumber'),
                    'location_idnumber' => new external_value(PARAM_INT, 'Location Idnumber')
				)
        );
    }
	
	/**
     * Parameters For Enroll User
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
	
	public static function enroll_user_parameters() {
        return new external_function_parameters(
			array(
				'username'        => new external_value(PARAM_TEXT,  'Username', VALUE_REQUIRED),
				'firstname'       => new external_value(PARAM_TEXT, 'User Firstname', VALUE_REQUIRED),
				'lastname'        => new external_value(PARAM_TEXT,  'User Lastname', VALUE_REQUIRED),
				'password'        => new external_value(PARAM_TEXT,  'User Password', VALUE_REQUIRED),
				'email'           => new external_value(PARAM_TEXT,  'User Email', VALUE_REQUIRED),
				'gp_idnumber'     => new external_value(PARAM_INT,  'Group Idnumber', VALUE_REQUIRED),
				'course_idnumber' => new external_value(PARAM_RAW,  'Course Idnumber', VALUE_REQUIRED),
				'startdate'       => new external_value(PARAM_RAW,  'Start Date', VALUE_REQUIRED)
				
			)
        );
    }

    /**
     * 
     *
     * Accept parameter and perform require action.
     * @return array An array of arrays
     * @since Moodle 2.5
     */
	 
	
    public static function enroll_user($username, $firstname, $lastname, $password, $email, $gp_idnumber, $course_idnumber, $startdate) {
        global $CFG, $DB;
		
		require_once($CFG->dirroot .'/user/lib.php');
        $params = self::validate_parameters(
						self::enroll_user_parameters(), 
						array(
							'username'        => $username, 
							'firstname'       => $firstname, 
							'lastname'        => $lastname, 
							'password'        => $password,
							'email'           => $email,
							'gp_idnumber'     => $gp_idnumber,
							'course_idnumber' => $course_idnumber,
							'startdate'       => $startdate
						)
					);
		
		$params      = (object)$params;
        $transaction = $DB->start_delegated_transaction();

        self::validate_context(context_system::instance());
		
		//Create User
		$user_record                    = new stdClass();
		$user_record->username          = $params->username;
		$user_record->firstname         = $params->firstname;
		$user_record->lastname          = $params->lastname;
		$user_record->password          = hash_internal_user_password($params->password);
		$user_record->email             = $params->email;
		$user_record->descriptiontrust  = 0;
		$user_record->description       = '';
		$user_record->descriptionformat = 1;
		$user_record->mnethostid        = 1;
		$user_record->confirmed         = 1;
		$user_record->timecreated       = time();
		$user_record->timemodified      = time();
		
		
		$userid = user_create_user($user_record, false);

		
		//Enroll User
		$select   = 'shortname = "student"';
		$roleid   = $DB->get_record_select('role', $select, null , $fields='id', $strictness=IGNORE_MISSING);
		
		$select   = 'idnumber = "'.$params->course_idnumber .'"';
		$courseid = $DB->get_record_select('course', $select, null , $fields='id', $strictness=IGNORE_MISSING);
		
		$instance_check = $DB->get_record('enrol', array('courseid' => $courseid->id, 'status' => 0, 'enrol' => 'manual'));
		
		$select   = 'idnumber = '.$params->gp_idnumber .'';
		$groupid   = $DB->get_record_select('groups', $select, null , $fields='id', $strictness=IGNORE_MISSING);
		
		if($instance_check) {
			$enrolmethodtype = 'manual';
			$context = context_course::instance($courseid->id);
			if (!is_enrolled($context, $userid)) {
				$enrol = enrol_get_plugin($enrolmethodtype);
				if ($enrol === null) {
					return false;
				}
				
				$instances = enrol_get_instances($courseid->id, true);
				$manualinstance = null;
				
				foreach ($instances as $instance) {
					if ($instance->name == $enrolmethodtype) {
						$manualinstance = $instance;
						break;
					}
				}
				
				if ($manualinstance !== null) {
					$instanceid = $enrol->add_default_instance($courseid->id);
					if ($instanceid === null) {
						$instanceid = $enrol->add_instance($courseid->id);
					}
					
					$instance = $DB->get_record('enrol', array('id' => $instanceid));
				}
					
				$enrol->enrol_user($instance, $userid, $roleid->id, strtotime($params->startdate));

			}
		}
		
		$enrol_group            = new stdClass();
		$enrol_group->groupid   = $groupid->id;
		$enrol_group->userid    = $userid;
		$enrol_group->itemid    = 0;
		$enrol_group->timeadded = time();
		
		$DB->insert_record('groups_members', $enrol_group);
		
        $transaction->allow_commit();

        return $params;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
	
	public static function enroll_user_returns() {
       return new external_single_structure(
            array(
                    'username'        => new external_value(PARAM_TEXT, 'Username'),
                    'firstname'       => new external_value(PARAM_TEXT, 'User Firstname'),
                    'lastname'        => new external_value(PARAM_TEXT, 'User Lastname'),
                    'password'        => new external_value(PARAM_TEXT, 'User Password'),
                    'email'           => new external_value(PARAM_TEXT, 'User Email'),
                    'gp_idnumber'     => new external_value(PARAM_INT, 'Group Idnumber'),
                    'course_idnumber' => new external_value(PARAM_RAW, 'Course Idnumber'),
                    'startdate'       => new external_value(PARAM_RAW, 'Start Date')	
				)
        );
    }
}
