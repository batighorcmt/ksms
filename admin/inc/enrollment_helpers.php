<?php
// Enrollment helpers: abstract per-year class/section/roll management for students

if (!function_exists('enrollment_table_exists')) {
    function enrollment_table_exists(PDO $pdo): bool {
        try {
            $pdo->query("SELECT 1 FROM students_enrollment LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('current_academic_year_id')) {
    function current_academic_year_id(PDO $pdo): ?int {
        try {
            $row = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            return $row && isset($row['id']) ? (int)$row['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('get_enrolled_students')) {
    /**
     * Fetch students enrolled in a class/section for a given academic year.
     * Falls back to legacy students table if enrollment table not present.
     * Returns array of [id, first_name, last_name, roll_number].
     */
    function get_enrolled_students(PDO $pdo, int $class_id, int $section_id, ?int $academic_year_id = null): array {
        $academic_year_id = $academic_year_id ?: current_academic_year_id($pdo);
        if (enrollment_table_exists($pdo) && $academic_year_id) {
            $stmt = $pdo->prepare(
                "SELECT s.id, s.first_name, s.last_name, se.roll_number
                 FROM students_enrollment se
                 JOIN students s ON s.id = se.student_id
                 WHERE se.class_id = ? AND se.section_id = ? AND se.academic_year_id = ?
                  AND (se.status = 'active' OR se.status IS NULL)
                 ORDER BY se.roll_number ASC, s.first_name ASC"
            );
            $stmt->execute([$class_id, $section_id, $academic_year_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        // Fallback legacy behavior
        // Detect presence of students.status column to avoid SQL errors on migrated schemas
        $hasStatusCol = false;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
            $hasStatusCol = $cols ? true : false;
        } catch (Exception $e) {
            $hasStatusCol = false;
        }
        if ($academic_year_id) {
            $sql = "SELECT id, first_name, last_name, roll_number FROM students WHERE class_id = ? AND section_id = ?" . ($hasStatusCol ? " AND status='active'" : "") . " AND year_id = ? ORDER BY roll_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$class_id, $section_id, $academic_year_id]);
        } else {
            $sql = "SELECT id, first_name, last_name, roll_number FROM students WHERE class_id = ? AND section_id = ?" . ($hasStatusCol ? " AND status='active'" : "") . " ORDER BY roll_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$class_id, $section_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('enroll_student')) {
    /** Insert or update a student's enrollment for a year; falls back to legacy columns when table missing */
    function enroll_student(PDO $pdo, int $student_db_id, int $academic_year_id, int $class_id, ?int $section_id, $roll_number): bool {
        if (enrollment_table_exists($pdo)) {
            // Ensure unique per student+year
            $sql = "INSERT INTO students_enrollment (student_id, academic_year_id, class_id, section_id, roll_number, status, created_at, updated_at)
                    VALUES (?,?,?,?,?, 'active', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), section_id = VALUES(section_id), roll_number = VALUES(roll_number), status = 'active', updated_at = NOW()";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$student_db_id, $academic_year_id, $class_id, $section_id, ($roll_number !== '' ? (int)$roll_number : null)]);
        }
        // Legacy fallback: update students table columns
        $stmt = $pdo->prepare("UPDATE students SET class_id = ?, section_id = ?, roll_number = ?, year_id = ? WHERE id = ?");
        return $stmt->execute([$class_id, $section_id, ($roll_number !== '' ? (int)$roll_number : null), $academic_year_id, $student_db_id]);
    }
}

if (!function_exists('promote_students')) {
    /**
     * Promote all students from one class/section/year to another class/section/year.
     * Requires students_enrollment table. Returns number of promoted records.
     */
    function promote_students(PDO $pdo, int $from_class_id, int $from_section_id, int $from_year_id, int $to_class_id, int $to_section_id, int $to_year_id): int {
        if (!enrollment_table_exists($pdo)) return 0;
        $pdo->beginTransaction();
        try {
            // Select source enrollments
            $src = $pdo->prepare("SELECT id, student_id FROM students_enrollment WHERE class_id = ? AND section_id = ? AND academic_year_id = ? AND (status = 'active' OR status IS NULL)");
            $src->execute([$from_class_id, $from_section_id, $from_year_id]);
            $rows = $src->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($rows)) { $pdo->commit(); return 0; }
            $ins = $pdo->prepare("INSERT INTO students_enrollment (student_id, academic_year_id, class_id, section_id, roll_number, status, promoted_from_enrollment_id, created_at, updated_at)
                                   VALUES (?,?,?,?, NULL, 'active', ?, NOW(), NOW())
                                   ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), section_id = VALUES(section_id), status = 'active', updated_at = NOW()");
            $count = 0;
            foreach ($rows as $r) {
                $ok = $ins->execute([(int)$r['student_id'], $to_year_id, $to_class_id, $to_section_id, (int)$r['id']]);
                if ($ok) $count++;
            }
            $pdo->commit();
            return $count;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return 0;
        }
    }
}

?>
