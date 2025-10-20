<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * This file contains the customcert element text's core interaction API.
 *
 * @package    customcertelement_textreplace
 * @copyright  2013 Mark Nelson <markn@moodle.com>  
 * @author     2025 Vanderson Farias <vanderson2005@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_textreplace;

/**
 * The customcert element text's core interaction API.
 *
 * @package    customcertelement_textreplace
 * @copyright  2013 Mark Nelson <markn@moodle.com>  
 * @author     2025 Vanderson Farias <vanderson2005@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element
{

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform)
    {
        $mform->addElement('textarea', 'text', get_string('text', 'customcertelement_text'));
        $mform->setType('text', PARAM_RAW);
        $mform->addHelpButton('text', 'text', 'customcertelement_text');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data)
    {
        return $data->text;
    }
    /**
     * 
     * Function to replace variables in the text with user data.
     * 
     */

    protected function get_user_field_value(\stdClass $user, $field): string
    {
        global $CFG, $DB;
        $valeu = '';

        if ($field = $DB->get_record('user_info_field', ['shortname' => $field])) {
            // Found the field name, let's update the value to display.
            $value = $field->name;
            $file = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
            if (file_exists($file)) {
                require_once($CFG->dirroot . '/user/profile/lib.php');
                require_once($file);
                $class = "profile_field_{$field->datatype}";
                $field = new $class($field->id, $user->id);
                $value = $field->display_data();
            }
        }
        $context = \mod_customcert\element_helper::get_context($this->get_id());
        return format_string($value, true, ['context' => $context]);
    }

    function get_customcoursefield_value(array $customfields, string $shortname)
    {
        foreach ($customfields as $data_controller) {
            $field = $data_controller->get_field();
            if ($field->get('shortname') === $shortname) {
                return $data_controller->get_value();
            }
        }
        return null;
    }

    public function replace_text($texto, $user, $context = [])
    {
        $replacer = function ($matches) use ($context, $user) {
            list($full, $table, $field, $fallback) = $matches + [null, null, null, ''];
            $value = '';

            // Recupera o courseid e dados do curso.
            $courseid = \mod_customcert\element_helper::get_courseid($this->id);
            $course = get_course($courseid);
            $customcourse = new \core_course_list_element($course);
            $customcourse = $customcourse->get_custom_fields();

            // Apenas para depuração — REMOVA depois.
            // error_log("Table: $table | Field: $field");

            switch ($table) {
                case 'user':
                    // Gera array com os campos customizados de perfil do usuário baseando no $user->id e retorna um array $user->profile
                    if (!empty($user->id)) {
                        global $CFG;
                        if (!function_exists('profile_load_data')) {
                            require_once($CFG->dirroot . '/user/profile/lib.php');
                        }
                        // profile_load_data aceita o usuário por referência e popula $user->profile
                        profile_load_data($user);
                    }

                    $value = $user->$field ?? ($this->get_user_field_value($user, $field) ?? '');


                    break;

                case 'course':
                    $value = $course->$field ?? ($this->get_customcoursefield_value($customcourse, $field) ?? '');
                    break;

                default:
                    // Se o contexto tiver essa tabela, tenta pegar direto.
                    if (isset($context[$table]) && is_object($context[$table])) {
                        $obj = $context[$table];
                        $value = $obj->$field ?? '';
                    }
                    break;
            }

            return ($value !== '') ? $value : $fallback;
        };

        // Regex: {tabela:campo|fallback}
        $pattern = '/\{([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)(?:\|([^}]*))?\}/';

        return preg_replace_callback($pattern, $replacer, $texto);
    }


    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user)
    {

        \mod_customcert\element_helper::render_content($pdf, $this, $this->replace_text($this->get_text(), $user));
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html()
    {
        global $USER;


        return \mod_customcert\element_helper::render_html_content($this, $this->replace_text($this->get_text(), $USER));
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform)
    {
        if (!empty($this->get_data())) {
            $element = $mform->getElement('text');
            $element->setValue($this->get_data());
        }
        parent::definition_after_data($mform);
    }

    /**
     * Helper function that returns the text.
     *
     * @return string
     */
    protected function get_text(): string
    {
        $context = \mod_customcert\element_helper::get_context($this->get_id());
        return format_text($this->get_data(), FORMAT_HTML, ['context' => $context]);
    }
}
