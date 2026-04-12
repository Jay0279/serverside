ALTER TABLE users
    ADD COLUMN student_id VARCHAR(50) NULL AFTER username;

ALTER TABLE users
    ADD CONSTRAINT uq_users_student_id UNIQUE (student_id);

ALTER TABLE users
    ADD CONSTRAINT chk_users_student_id_required_for_students
    CHECK (
        role <> 'student'
        OR (student_id IS NOT NULL AND student_id <> '')
    );
