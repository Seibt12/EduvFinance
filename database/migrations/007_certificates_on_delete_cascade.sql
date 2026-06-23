DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'certificates'
    ) THEN
        ALTER TABLE certificates DROP CONSTRAINT IF EXISTS certificates_course_id_fkey;
        ALTER TABLE certificates
            ADD CONSTRAINT certificates_course_id_fkey
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

        ALTER TABLE certificates DROP CONSTRAINT IF EXISTS certificates_user_id_fkey;
        ALTER TABLE certificates
            ADD CONSTRAINT certificates_user_id_fkey
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;
END $$;
