<?php
if (! defined('BASE_PATH'))
    exit('No direct script access allowed');
/**
 * eduTrac SIS Student Functions
 *
 * @license GPLv3
 *         
 * @since 6.2.3
 * @package eduTrac SIS
 * @author Joshua Parker <joshmac3@icloud.com>
 */

$app = \Liten\Liten::getInstance();

/**
 * A function which returns true if the logged in user
 * is a student in the system.
 *
 * @since 4.3
 * @param int $id
 *            Student's ID.
 * @return bool
 */
function isStudent($id)
{
    if ('' == _trim($id)) {
        $message = _t('Invalid student ID: Empty ID given.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    if (! is_numeric($id)) {
        $message = _t('Invalid student ID: student id must be numeric.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    $stu = get_person_by('personID', $id);
    
    if ($stu->stuID != '') {
        return true;
    }
    return false;
}

/**
 * If the logged in user is not a student,
 * hide the menu item.
 * For myeduTrac usage.
 *
 * @since 4.3
 * @param int $id
 *            Person ID
 * @return string
 */
function checkStuMenuAccess($id)
{
    if ('' == _trim($id)) {
        $message = _t('Invalid person ID: empty ID given.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    if (! is_numeric($id)) {
        $message = _t('Invalid person ID: person id must be numeric.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    if (! isStudent($id)) {
        return ' style="display:none !important;"';
    }
}

/**
 * If the logged in user is not a student,
 * redirect the user to his/her profile.
 *
 * @since 4.3
 * @param int $id
 *            Person ID.
 * @return mixed
 */
function checkStuAccess($id)
{
    if ('' == _trim($id)) {
        $message = _t('Invalid student ID: empty ID given.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    if (! is_numeric($id)) {
        $message = _t('Invalid student ID: student id must be numeric.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    return isStudent($id);
}

function studentsExist($id)
{
    $app = \Liten\Liten::getInstance();
    $stu = $app->db->query("SELECT * FROM stu_course_sec WHERE courseSecID = ?", [
        $id
    ]);
    $q = $stu->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    if (count($q[0]['id']) > 0) {
        return true;
    } else {
        return false;
    }
}

/**
 *
 * @since 4.0.7
 */
function getstudentload($term, $creds, $level)
{
    $app = \Liten\Liten::getInstance();
    $t = explode("/", $term);
    $newTerm1 = $t[0];
    $newTerm2 = $t[1];
    $sql = $app->db->query("SELECT
                        status
                    FROM student_load_rule
                    WHERE term REGEXP CONCAT('[[:<:]]', ?, '[[:>:]]')
                    OR term REGEXP CONCAT('[[:<:]]', ?, '[[:>:]]')
                    AND acadLevelCode REGEXP CONCAT('[[:<:]]', ?, '[[:>:]]')
                    AND ?
                    BETWEEN min_cred
                    AND max_cred
                    AND active = '1'", [
        $newTerm1,
        $newTerm2,
        $level,
        $creds
    ]);
    $q = $sql->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    foreach ($q as $r) {
        return $r['status'];
    }
}

function student_has_restriction()
{
    $app = \Liten\Liten::getInstance();
    $rest = $app->db->query("SELECT
        				GROUP_CONCAT(DISTINCT c.deptName SEPARATOR ',') AS 'Restriction'
    				FROM restriction a
					LEFT JOIN restriction_code b ON a.rstrCode = b.rstrCode
					LEFT JOIN department c ON b.deptCode = c.deptCode
					WHERE a.severity = '99'
					AND a.endDate <= '0000-00-00'
					AND a.stuID = ?
					GROUP BY a.stuID
					HAVING a.stuID = ?", [
        get_persondata('personID'),
        get_persondata('personID')
    ]);
    $q = $rest->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    if (count($q[0]['Restriction']) > 0) {
        foreach ($q as $r) {
            return '<strong>' . $r['Restriction'] . '</strong>';
        }
    } else {
        return false;
    }
}

/**
 * is_ferpa function added to check for
 * active FERPA restrictions for students.
 *
 * @since 4.5
 * @param int $id
 *            Student's ID.
 */
function is_ferpa($id)
{
    if ('' == _trim($id)) {
        $message = _t('Invalid student ID: empty ID given.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    if (! is_numeric($id)) {
        $message = _t('Invalid student ID: student id must be numeric.');
        _incorrectly_called(__FUNCTION__, $message, '6.2.0');
        return;
    }
    
    $app = \Liten\Liten::getInstance();
    
    $ferpa = $app->db->query("SELECT
                        rstrID
                    FROM restriction
                    WHERE stuID = ?
                    AND rstrCode = 'FERPA'
                    AND (endDate = '' OR endDate = '0000-00-00')", [
        $id
    ]);
    
    $q = $ferpa->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    
    if (count($q) > 0) {
        return _t('Yes');
    } else {
        return _t('No');
    }
}

function getStuSec($code, $term)
{
    $app = \Liten\Liten::getInstance();
    $stcs = $app->db->stu_course_sec()
        ->where('stuID = ?', get_persondata('personID'))
        ->_and_()
        ->where('courseSecCode = ?', $code)
        ->_and_()
        ->where('termCode = ?', $term)
        ->findOne();
    
    if ($stcs !== false) {
        return ' style="display:none;"';
    }
}

function isRegistrationOpen()
{
    if (get_option('open_registration') == 0 || ! isStudent(get_persondata('personID'))) {
        return ' style="display:none !important;"';
    }
}

/**
 * Graduated Status: if the status on a student's program
 * is "G", then the status and status dates are disabled.
 *
 * @since 1.0.0
 * @param
 *            string
 * @return mixed
 */
function gs($s)
{
    if ($s == 'G') {
        return ' readonly="readonly"';
    }
}

/**
 * Calculates grade points for stu_acad_cred.
 *
 * @param string $grade
 *            Letter grade.
 * @param float $credits
 *            Number of course credits.
 * @return mixed
 */
function acadCredGradePoints($grade, $credits)
{
    $app = \Liten\Liten::getInstance();
    $gp = $app->db->grade_scale()
        ->select('points')
        ->where('grade = ?', $grade);
    $q = $gp->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    foreach ($q as $r) {
        $gradePoints = $r['points'] * $credits;
    }
    return $gradePoints;
}

/**
 * Checks to see if the logged in student can
 * register for courses.
 *
 * @return bool
 */
function student_can_register()
{
    $app = \Liten\Liten::getInstance();
    $stcs = $app->db->query("SELECT
                        COUNT(courseSecCode) AS Courses
                    FROM stu_course_sec
                    WHERE stuID = ?
                    AND termCode = ?
                    AND status IN('A','N')
                    GROUP BY stuID,termCode", [
        get_persondata('personID'),
        get_option('registration_term')
    ]);
    $q = $stcs->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    foreach ($q as $r) {
        $courses = $r['Courses'];
    }
    
    $rstr = $app->db->query("SELECT *
                    FROM restriction
                    WHERE severity = '99'
                    AND stuID = ?
                    AND endDate = '0000-00-00'
                    OR endDate > ?", [
        get_persondata('personID'),
        date('Y-m-d')
    ]);
    $sql1 = $rstr->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    
    $stu = $app->db->query("SELECT
        				a.ID
    				FROM
    					student a
					LEFT JOIN
						stu_program b
					ON
						a.stuID = b.stuID
					WHERE
						a.stuID = ?
					AND
						a.status = 'A'
					AND
						b.currStatus = 'A'", [
        get_persondata('personID')
    ]);
    
    $sql2 = $stu->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    
    if ($courses != NULL && $courses >= get_option('number_of_courses')) {
        return false;
    } elseif (count($sql1[0]['rstrID']) > 0) {
        return false;
    } elseif (count($sql2[0]['ID']) <= 0) {
        return false;
    } else {
        return true;
    }
}

/**
 * Checks to see if there is a preReq on
 * the course the student is registering for.
 * If there is one, then we do a check to see
 * if the student has meet the preReq.
 *
 * @param int $stuID
 *            Student ID.
 * @param int $courseSecID
 *            ID of course section.
 * @return bool
 */
function prerequisite($stuID, $courseSecID)
{
    $app = \Liten\Liten::getInstance();
    $crse = $app->db->query("SELECT
    					a.preReq
					FROM course a
					LEFT JOIN course_sec b ON a.courseID = b.courseID
					WHERE b.courseSecID = ?", [
        $courseSecID
    ]);
    $q1 = $crse->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    $array = [];
    foreach ($q1 as $r1) {
        $array[] = $r1;
    }
    $req = explode(",", $r1['preReq']);
    if (count($q1[0]['preReq']) > 0) {
        $stac = $app->db->query("SELECT
	    					stuAcadCredID
						FROM stu_acad_cred
						WHERE courseCode IN('" . str_replace(",", "', '", $r1['preReq']) . "')
						AND stuID = ?
						AND status IN('A','N')
						AND grade <> ''
						AND grade <> 'W'
						AND grade <> 'I'
						AND grade <> 'F'
						GROUP BY stuID,courseCode", [
            $stuID
        ]);
        $q2 = $stac->find(function ($data) {
            $array = [];
            foreach ($data as $d) {
                $array[] = $d;
            }
            return $array;
        });
    }
    if (empty($r1['preReq']) || count($req) == count($q2)) {
        return true;
    }
}

/**
 *
 * @since 4.4
 */
function shoppingCart()
{
    $app = \Liten\Liten::getInstance();
    $cart = $app->db->stu_rgn_cart()->where('stuID = ?', get_persondata('personID'));
    $q = $cart->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    if (count($q[0]['stuID']) > 0) {
        return true;
    }
}

/**
 *
 * @since 4.4
 */
function removeFromCart($section)
{
    $app = \Liten\Liten::getInstance();
    $cart = $app->db->stu_rgn_cart()
        ->where('stuID = ?', get_persondata('personID'))
        ->_and_()
        ->whereGte('deleteDate', $app->db->NOW())
        ->_and_()
        ->where('courseSecID = ?', $section);
    $q = $cart->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    if (count($q[0]['stuID']) > 0) {
        return true;
    }
}

/**
 * Retrieves all the tags from every student
 * and removes duplicates.
 *
 * @since 6.0.04
 * @return mixed
 */
function tagList()
{
    $app = \Liten\Liten::getInstance();
    $tagging = $app->db->query('SELECT tags FROM student');
    $q = $tagging->find(function ($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    $tags = [];
    foreach ($q as $r) {
        $tags = array_merge($tags, explode(",", $r['tags']));
    }
    $tags = array_unique_compact($tags);
    foreach ($tags as $key => $value) {
        if ($value == "" || strlen($value) <= 0) {
            unset($tags[$key]);
        }
    }
    return $tags;
}

/**
 * Retrieve student's FERPA restriction status.
 *
 * @return Student's FERPA restriction status.
 */
function get_stu_restriction($stu_id)
{
    $app = \Liten\Liten::getInstance();
    $rest = $app->db->query("SELECT
                        b.rstrCode,a.severity,b.description,c.deptEmail,c.deptPhone,c.deptName,
        				GROUP_CONCAT(DISTINCT b.rstrCode SEPARATOR ',') AS 'Restriction'
    				FROM restriction a
					LEFT JOIN restriction_code b ON a.rstrCode = b.rstrCode
					LEFT JOIN department c ON b.deptCode = c.deptCode
					WHERE a.endDate <= '0000-00-00'
					AND a.stuID = ?
                    AND a.rstrCode <> 'FERPA'
					GROUP BY a.rstrCode,a.stuID
					HAVING a.stuID = ?", [ $stu_id, $stu_id]
        );
    $q = $rest->find(function($data) {
        $array = [];
        foreach ($data as $d) {
            $array[] = $d;
        }
        return $array;
    });
    
    return $q;
}

function get_stu_header($stu_id)
{
    $student = get_student($stu_id);

?>

<!-- List Widget -->
<div class="relativeWrap">
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading glyphicons user"><i></i><?= get_name(_h($student->stuID)); ?></h4>&nbsp;&nbsp;
            <?php if(!isset($_COOKIE['SWITCH_USERBACK']) && _h($student->stuID) != get_persondata('personID')) : ?>
            <span<?=ae('login_as_user');?> class="label label-inverse"><a href="<?=get_base_url();?>switchUserTo/<?=_h($student->stuID);?>/"><font color="#FFFFFF"><?= _t('Switch To'); ?></font></a></span>
            <?php endif; ?>
            <?php if(get_persondata('personID') == $student->stuID && !hasPermission('access_dashboard')) : ?>
            <a href="<?= get_base_url(); ?>profile/" class="heading pull-right"><?= _h($student->stuID); ?></a>
            <?php else : ?>
            <a href="<?=get_base_url();?>stu/<?=_h($student->stuID);?>/" class="heading pull-right"><?=_h($student->stuID);?></a>
            <?php endif; ?>
        </div>
        <div class="widget-body">
            <!-- 3 Column Grid / One Third -->
            <div class="row">

                <!-- One Third Column -->
                <div class="col-md-2">
                    <?= getSchoolPhoto($student->stuID, $student->email1, '90'); ?>
                </div>
                <!-- // One Third Column END -->

                <!-- One Third Column -->
                <div class="col-md-3">
                    <p><?= _h($student->address1); ?> <?= _h($student->address2); ?></p>
                    <p><?= _h($student->city); ?> <?= _h($student->state); ?> <?= _h($student->zip); ?></p>
                    <p><strong><?= _t('Phone:'); ?></strong> <?= _h($student->phone1); ?></p>
                </div>
                <!-- // One Third Column END -->

                <!-- One Third Column -->
                <div class="col-md-3">
                    <p><strong><?= _t('Email:'); ?></strong> <a href="mailto:<?= _h($student->email1); ?>"><?= _h($student->email1); ?></a></p>
                    <p><strong><?= _t('Birth Date:'); ?></strong> <?= (_h($student->dob) > '0000-00-00' ? date('D, M d, o', strtotime(_h($student->dob))) : ''); ?></p>
                    <p><strong><?= _t('Status:'); ?></strong> <?=(_h($student->stuStatus) == 'A') ? _t( 'Active' ) : _t( 'Inactive' );?></p>
                </div>
                <!-- // One Third Column END -->

                <!-- One Third Column -->
                <div class="col-md-3">
                    <p><strong><?= _t('FERPA:'); ?></strong> <?= is_ferpa(_h($student->stuID)); ?> 
                            <?php if (is_ferpa(_h($student->stuID)) == 'Yes') : ?>
                            <a href="#FERPA" data-toggle="modal"><img style="vertical-align:top !important;" src="<?= get_base_url(); ?>static/common/theme/images/exclamation.png" /></a>
                            <?php else : ?>
                            <a href="#FERPA" data-toggle="modal"><img style="vertical-align:top !important;" src="<?= get_base_url(); ?>static/common/theme/images/information.png" /></a>
                        <?php endif; ?>
                    </p>
                    <p><strong><?= _t('Restriction(s):'); ?></strong> 
                        <?php $prefix = ''; foreach (get_stu_restriction($student->stuID) as $v) : ?>
                            <?=$prefix;?><span data-toggle="popover" data-title="<?= _h($v['description']); ?>" data-content="Contact: <?= _h($v['deptName']); ?> <?= (_h($v['deptEmail']) != '') ? ' | ' . $v['deptEmail'] : ''; ?><?= (_h($v['deptPhone']) != '') ? ' | ' . $v['deptPhone'] : ''; ?><?= (_h($v['severity']) == 99) ? _t(' | Restricted from registering for courses.') : ''; ?>" data-placement="bottom"><a href="#"><?= _h($v['Restriction']); ?></a></span>
                        <?php $prefix = ', '; endforeach; ?>
                    </p>
                    <p><strong><?= _t('Entry Date:'); ?></strong> <?= date('D, M d, o', strtotime(_h($student->stuAddDate))); ?></p>
                </div>
                <!-- // One Third Column END -->

            </div>
            <!-- // 3 Column Grid / One Third END -->
        </div>
    </div>
</div>
<!-- // List Widget END -->

<!-- Modal -->
<div class="modal fade" id="FERPA">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal heading -->
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?=_t( 'Family Educational Rights and Privacy Act (FERPA)' );?></h3>
            </div>
            <!-- // Modal heading END -->
            <!-- Modal body -->
            <div class="modal-body">
                <p><?=_t('"FERPA gives parents certain rights with respect to their children\'s education records. 
                These rights transfer to the student when he or she reaches the age of 18 or attends a school beyond 
                the high school level. Students to whom the rights have transferred are \'eligible students.\'"');?></p>
                <p><?=_t('If the FERPA restriction states "Yes", then the student has requested that none of their 
                information be given out without their permission. To get a better understanding of FERPA, visit 
                the U.S. DOE\'s website @ ') . 
                '<a href="http://www2.ed.gov/policy/gen/guid/fpco/ferpa/index.html">http://www2.ed.gov/policy/gen/guid/fpco/ferpa/index.html</a>.';?></p>
            </div>
            <!-- // Modal body END -->
            <!-- Modal footer -->
            <div class="modal-footer">
                <a href="#" class="btn btn-default" data-dismiss="modal"><?=_t( 'Close' );?></a> 
            </div>
            <!-- // Modal footer END -->
        </div>
    </div>
</div>
<!-- // Modal END -->

<?php
}

/**
 * Retrieves student data given a student ID or student array.
 *
 * @since 6.2.3
 * @param int|etsis_Student|null $student
 *            Student ID or student array.
 * @param bool $object
 *            If set to true, data will return as an object, else as an array.
 */
function get_student($student, $object = true)
{
    if ($student instanceof \app\src\Core\etsis_Student) {
        $_student = $student;
    } elseif (is_array($student)) {
        if (empty($student['stuID'])) {
            $_student = new \app\src\Core\etsis_Student($student);
        } else {
            $_student = \app\src\Core\etsis_Student::get_instance($student['stuID']);
        }
    } else {
        $_student = \app\src\Core\etsis_Student::get_instance($student);
    }
    
    if (! $_student) {
        return null;
    }
    
    if ($object == true) {
        $_student = array_to_object($_student);
    }
    
    return $_student;
}