ALTER TABLE captains
    ADD COLUMN academic_qualification VARCHAR(255) NULL AFTER email,
    ADD COLUMN certificate_image_path VARCHAR(255) NULL AFTER ssn_card_path;
