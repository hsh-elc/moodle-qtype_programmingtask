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

defined('MOODLE_INTERNAL') || die();

namespace qtype_programmingtask\utility\communicator;

use qtype_programmingtask\exceptions\grappa_exception;

require_once($CFG->libdir . '/filelib.php');

class grappa_communicator implements communicator_interface {

    private $grappaurl;
    private $grappatimeout;
    private $lmsid;
    private $lmspw;

    public function get_graders(): array {
        $url = "{$this->grappaurl}/graders";
        list($gradersjson, $httpstatuscode) = $this->get_from_grappa($url);

        if ($httpstatuscode != 200) {
            throw new grappa_exception("Received HTTP status code $httpstatuscode when accessing URL GET $url");
        }
        return json_decode($gradersjson, true);
    }

    public function is_task_cached($uuid): bool {
        $url = "{$this->grappaurl}/tasks/$uuid";
        list(, $httpstatuscode) = $this->head_from_grappa($url);

        if ($httpstatuscode == 200) {
            return true;
        } else if ($httpstatuscode == 404) {
            return false;
        } else {
            throw new grappa_exception("Received HTTP status code $httpstatuscode when accessing URL HEAD $url");
        }
    }

    public function enqueue_submission(string $graderid, bool $asynch, \stored_file $submissionfile) {
        $url = "{$this->grappaurl}/{$this->lmsid}/gradeprocesses?graderid=$graderid&async=$asynch";
        $params = array('submission' => $submissionfile);

        list($responsejson, $httpstatuscode) = $this->post_to_grappa($url, $params);
        if ($httpstatuscode != 201 /* = CREATED */) {
            throw new grappa_exception("Received HTTP status code $httpstatuscode when accessing URL POST $url");
        }
        return json_decode($responsejson)->gradeProcessId;
    }

    public function get_grading_result(string $graderid, string $gradeprocessid) {
        $url = "{$this->grappaurl}/$graderid/gradeprocesses/$gradeprocessid";
        list($response, $httpstatuscode) = $this->get_from_grappa($url);
        if ($httpstatuscode == 202) {
            return false;
        } else if ($httpstatuscode == 200) {
            return $response;
        } else {
            throw new grappa_exception("Received HTTP status code $httpstatuscode when accessing URL POST $url");
        }
    }

    /*
     *
     *
     * Utility functions to access grappa from here on
     *
     *
     *
     */

    private function get_from_grappa($url, $params = array(), $options = array()) {
        $curl = new \curl();
        if (!isset($options['CURLOPT_TIMEOUT'])) {
            $options['CURLOPT_TIMEOUT'] = $this->grappatimeout;
        }
        /*
         *
         * TODO: AUTHENTICATION
         *
         */
        $response = $curl->get($url, $params, $options);

        $info = $curl->get_info();
        $errno = $curl->get_errno();
        if ($errno != 0) {
            // Errno indicates errors on transport level therefore this is almost certainly an error we do not want
            // http errors need to be handled by each calling function individually.
            throw new \invalid_response_exception("Error accessing GET $url;  CURL error code: $errno;  Error: {$curl->error}");
        }

        return array($response, $info['http_code']);
    }

    private function head_from_grappa($url, $options = array()) {
        $curl = new \curl();
        if (!isset($options['CURLOPT_TIMEOUT'])) {
            $options['CURLOPT_TIMEOUT'] = $this->grappatimeout;
        }

        /*
         *
         * TODO: AUTHENTICATION
         *
         */
        $response = $curl->head($url, $options);

        $info = $curl->get_info();
        $errno = $curl->get_errno();
        if ($errno != 0) {
            // Errno indicates errors on transport level therefore this is almost certainly an error we do not want
            // http errors need to be handled by each calling function individually.
            throw new \invalid_response_exception("Error accessing HEAD $url;  CURL error code: $errno;  Error: {$curl->error}");
        }

        return array($response, $info['http_code']);
    }

    private function post_to_grappa($url, $params = array(), $options = array()) {
        $curl = new \curl();
        if (!isset($options['CURLOPT_TIMEOUT'])) {
            $options['CURLOPT_TIMEOUT'] = $this->grappatimeout;
        }
        /*
         *
         * TODO: AUTHENTICATION
         *
         */
        $response = $curl->post($url, $params, $options);

        $info = $curl->get_info();
        $errno = $curl->get_errno();
        if ($errno != 0) {
            // Errno indicates errors on transport level therefore this is almost certainly an error we do not want
            // http errors need to be handled by each calling function individually.
            throw new \invalid_response_exception("Error accessing POST $url;  CURL error code: $errno;  Error: {$curl->error}");
        }

        return array($response, $info['http_code']);
    }

    /*
     *
     *
     * Singleton related code from here on
     *
     *
     */

    protected function __construct() {
        $this->grappaurl = get_config("qtype_programmingtask", "grappa_url");
        $this->grappatimeout = get_config("qtype_programmingtask", "grappa_timeout");
        /*
         * TODO: Put both into some kind of config var?
         */
        $this->lmsid = "moodle";
        $this->lmspw = "foo";
    }

    protected static $instance = null;

    public static function get_instance(): grappa_communicator {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    protected function __clone() {
    }

}
