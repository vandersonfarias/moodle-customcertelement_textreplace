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
 * @modified   2026 Bruno Ribeiro Silva <ribeirosilva.bruno@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_textreplace;

/**
 * The customcert element text's core interaction API.
 *
 * @package    customcertelement_textreplace
 * @copyright  2013 Mark Nelson <markn@moodle.com>  
 * @author     2025 Vanderson Farias <vanderson2005@gmail.com>
 * @modified   2026 Bruno Ribeiro Silva <ribeirosilva.bruno@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin Text Replace (Novas funções implementadas com novos campos)
 * Busca data em: Matrículas -> Papéis -> Logs (Fallback triplo)
 */
class element extends \mod_customcert\element {

    public function render_form_elements($mform) {
        $mform->addElement('textarea', 'text', 'Texto do Certificado');
        $mform->setType('text', PARAM_RAW);
        parent::render_form_elements($mform);
    }

    public function save_unique_data($data) {
        return isset($data->text) ? $data->text : '';
    }

    protected function load_user_profile($user) {
        global $CFG;
        if (isset($user->profile)) return $user;
        if (file_exists($CFG->dirroot . '/user/profile/lib.php')) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            if (function_exists('profile_load_data')) {
                profile_load_data($user);
            }
        }
        return $user;
    }

    protected function get_course_grade($courseid, $userid) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $grade = \grade_get_course_grade($userid, $courseid);
        if ($grade && isset($grade->str_grade)) {
            return $grade->str_grade;
        }
        return '';
    }

    protected function get_teachers($courseid) {
        $context = \context_course::instance($courseid);
        $teachers = get_role_users(3, $context, false, 'u.firstname, u.lastname'); 
        if (!$teachers) {
            $teachers = get_users_by_capability($context, 'moodle/course:update', 'u.firstname, u.lastname');
        }
        $names = [];
        foreach ($teachers as $t) {
            $names[] = fullname($t);
        }
        return implode(', ', $names);
    }

    /**
     * NOVA LÓGICA DE DATA DE MATRÍCULA (Robustez para Admins)
     */
    protected function get_enrollment_date($courseid, $userid, $format) {
        global $DB;
        
        // 1. TENTATIVA PADRÃO: Tabela de Matrículas (Alunos normais)
        // Busca a data de início mais antiga (caso tenha mais de uma)
        $sql = "SELECT ue.timestart FROM {user_enrolments} ue 
                JOIN {enrol} e ON e.id = ue.enrolid 
                WHERE e.courseid = :courseid AND ue.userid = :userid 
                ORDER BY ue.timestart ASC";
        // get_records com limit 1 é mais seguro que get_record para evitar erros de múltiplos resultados
        $enrols = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid], 0, 1);
        
        if ($enrols) {
            $enrol = reset($enrols);
            if (!empty($enrol->timestart)) {
                return $this->smart_date_format_public($enrol->timestart, $format);
            }
        }
        
        // 2. TENTATIVA SECUNDÁRIA: Tabela de Atribuição de Papéis (Para Admins/Professores Manuais)
        // Se você deu o cargo de "Professor" direto, está aqui.
        $context = \context_course::instance($courseid);
        $sql_role = "SELECT timemodified FROM {role_assignments} 
                     WHERE contextid = :contextid AND userid = :userid 
                     ORDER BY timemodified ASC";
        $roles = $DB->get_records_sql($sql_role, ['contextid' => $context->id, 'userid' => $userid], 0, 1);
        
        if ($roles) {
            $role = reset($roles);
            return $this->smart_date_format_public($role->timemodified, $format);
        }

        // 3. TENTATIVA FINAL: Logs (Primeiro acesso ao curso)
        // Útil se o usuário é Admin Global e não tem papel atribuído no curso, apenas acessou.
        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
             $sql_log = "SELECT timecreated FROM {logstore_standard_log} 
                         WHERE courseid = :courseid AND userid = :userid 
                         ORDER BY timecreated ASC";
             $logs = $DB->get_records_sql($sql_log, ['courseid' => $courseid, 'userid' => $userid], 0, 1);
             if ($logs) {
                 $log = reset($logs);
                 return $this->smart_date_format_public($log->timecreated, $format);
             }
        }
        
        return ''; // Se falhar tudo, retorna vazio
    }

    protected function get_user_role($courseid, $userid) {
        $context = \context_course::instance($courseid);
        $roles = get_user_roles($context, $userid);
        $role_names = [];
        foreach ($roles as $role) {
            $role_names[] = role_get_name($role, $context);
        }
        return implode(', ', $role_names);
    }

    protected function smart_date_format($timestamp, $format) {
        if (empty($timestamp)) return '';
        if (empty($format)) return userdate($timestamp, get_string('strftimedate', 'langconfig'));
        if (strpos($format, '%') !== false) {
            return userdate($timestamp, $format);
        }
        return date($format, $timestamp);
    }

    protected function process_content($texto, $user, $is_preview) {
        if (empty($texto) || !is_string($texto)) return $texto;

        // --- MODO PREVIEW ---
        if ($is_preview) {
            $dummies = [
                '{date}' => date('d/m/Y'),
                '{studentname}' => 'Aluno Teste',
                '{userid}' => '999',
                '{course:id}' => '10',
                '{code}' => 'PREVIEW-CODE'
            ];
            $texto = str_replace(array_keys($dummies), array_values($dummies), $texto);
            return preg_replace('/\{[a-zA-Z0-9_]+:[^}]+\}/', '[DADO]', $texto);
        }

        // --- MODO REAL ---
        try {
            global $DB, $COURSE;
            $user = $this->load_user_profile($user);
            
            $courseid = 0;
            $issue = new \stdClass();
            $issue->timecreated = time(); 
            $issue->code = 'PENDING';

            if ($this->get_id()) {
                $courseid = \mod_customcert\element_helper::get_courseid($this->get_id());
                $record = $DB->get_record_sql("SELECT ci.timecreated, ci.code FROM {customcert_issues} ci JOIN {customcert_elements} e ON e.pageid = (SELECT pageid FROM {customcert_elements} WHERE id = :elemid) WHERE e.id = :elementid AND ci.userid = :userid", ['elemid' => $this->get_id(), 'elementid' => $this->get_id(), 'userid' => $user->id], IGNORE_MISSING);
                if ($record) $issue = $record;
            }
            
            if (empty($courseid) && isset($COURSE->id)) {
                $courseid = $COURSE->id;
            }
            $course = get_course($courseid);

            $that = $this;
            $pattern = '/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_\s\/\-\.,%\\\\:]+))?(?:\|([^}]*))?\}/';

            $replacer = function ($matches) use ($user, $issue, $course, $that) {
                $key = strtoupper($matches[1] ?? ''); 
                $modifier = $matches[2] ?? null;
                $fallback = $matches[3] ?? '';
                $val = '';

                switch ($key) {
                    case 'USERID':
                    case 'ID':
                        return $user->id;

                    case 'DATE':
                    case 'ISSUEDATE':
                        return $that->smart_date_format_public((int)$issue->timecreated, $modifier);
                    case 'CODE':
                    case 'CERTIFICATECODE':
                    case 'CERTCODE':
                        return $issue->code;
                    case 'URL': 
                    case 'CERTURL':
                         return new \moodle_url('/mod/customcert/verify_certificate.php', ['code' => $issue->code]);

                    case 'COURSEID': 
                        return $course->id;
                    case 'COURSENAME':
                    case 'COURSE':
                        if ($modifier === 'id') return $course->id; 
                        return $course->fullname;
                    case 'TEACHERS':
                        return $that->get_teachers_public($course->id);
                    case 'GRADE': 
                        return $that->get_course_grade_public($course->id, $user->id);
                    case 'HOURS':
                         if (class_exists('\core_customfield\handler')) {
                            $h = \core_customfield\handler::get_handler('core_course', 'course');
                            $d = $h->export_instance_data_object($course->id);
                            if (isset($d->hours)) return $d->hours;
                            if (isset($d->cargahoraria)) return $d->cargahoraria;
                         }
                         return '';

                    case 'USERNAME': return $user->username;
                    case 'IDNUMBER': return $user->idnumber;
                    case 'FIRSTNAME': return $user->firstname;
                    case 'LASTNAME': return $user->lastname;
                    case 'EMAIL': return $user->email;
                    case 'PHONE1': return $user->phone1;
                    case 'PHONE2': return $user->phone2;
                    case 'INSTITUTION': return $user->institution;
                    case 'DEPARTMENT': return $user->department;
                    case 'ADDRESS': return $user->address;
                    case 'CITY': return $user->city;
                    case 'COUNTRY': 
                        return get_string($user->country, 'countries');
                    case 'TIMESTART': 
                        return $that->get_enrollment_date_public($course->id, $user->id, $modifier);
                    case 'USERROLENAME':
                        return $that->get_user_role_public($course->id, $user->id);
                    case 'STUDENTNAME':
                    case 'USER': 
                        $k = strtolower($key);
                        $f = strtolower($modifier ?: $k);
                        if ($f === 'fullname' || $key === 'STUDENTNAME') return fullname($user);
                        if (isset($user->$f)) return $user->$f;
                        if (isset($user->profile[$f])) return $user->profile[$f];
                        if (isset($user->{"profile_field_".$f})) return $user->{"profile_field_".$f};
                        break;
                }

                if ($val !== '' && $val !== null) return $val;
                
                $lowerKey = strtolower($key);
                if (isset($user->$lowerKey)) return $user->$lowerKey;
                if (isset($user->profile[$lowerKey])) return $user->profile[$lowerKey];

                if ($fallback !== '') return $fallback;
                return $matches[0];
            };

            return preg_replace_callback($pattern, $replacer, $texto);

        } catch (\Throwable $e) { return "Erro: " . $e->getMessage(); }
    }

    public function render_html() {
        global $USER;
        try {
            $raw = (string)$this->get_data();
            $content = $this->process_content_public($raw, $USER, true); 
            return \mod_customcert\element_helper::render_html_content($this, $content);
        } catch (\Throwable $e) { return "Erro Visual"; }
    }

    public function render($pdf, $preview, $user) {
        try {
            $raw = (string)$this->get_data();
            $content = $this->process_content_public($raw, $user, false);
            \mod_customcert\element_helper::render_content($pdf, $this, $content);
        } catch (\Throwable $e) { \mod_customcert\element_helper::render_content($pdf, $this, "Erro PDF"); }
    }

    public function definition_after_data($mform) {
        if ($data = $this->get_data()) {
            $mform->getElement('text')->setValue($data);
        }
        parent::definition_after_data($mform);
    }
    
    public function smart_date_format_public($t, $f) { return $this->smart_date_format($t, $f); }
    public function process_content_public($t, $u, $p) { return $this->process_content($t, $u, $p); }
    public function get_teachers_public($c) { return $this->get_teachers($c); }
    public function get_course_grade_public($c, $u) { return $this->get_course_grade($c, $u); }
    public function get_enrollment_date_public($c, $u, $f) { return $this->get_enrollment_date($c, $u, $f); }
    public function get_user_role_public($c, $u) { return $this->get_user_role($c, $u); }
}
    protected function get_text(): string
    {
        $context = \mod_customcert\element_helper::get_context($this->get_id());
        return format_text($this->get_data(), FORMAT_HTML, ['context' => $context]);
    }
}
