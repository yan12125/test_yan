DELIMITER //

-- Use procedures instead of functions becuase of the need to return multiple values

DROP PROCEDURE IF EXISTS select_text; //

CREATE PROCEDURE select_text(IN title_ VARCHAR(256), INOUT index_ INTEGER, OUT text_ VARCHAR(65536))
BEGIN
    -- "Exceptions" in mysql
    -- http://www.devshed.com/c/a/MySQL/Using-the-SIGNAL-Statement-for-Error-Handling/
    DECLARE index_out_of_range CONDITION FOR SQLSTATE '99001';
    DECLARE title_not_found CONDITION FOR SQLSTATE '99002';
    DECLARE lineCount INTEGER;
    DECLARE result VARCHAR(65536);
    SET lineCount = (SELECT `lines` FROM texts WHERE title = title_);
    IF lineCount IS NULL THEN
        SIGNAL title_not_found SET MESSAGE_TEXT = "Title not found";
    END IF;
    IF (index_ = -1) THEN
        -- RAND() is guaranteed to be in [0.0, 1.0)
        SET index_ = FLOOR(RAND() * lineCount);
    ELSEIF (index_ >= lineCount OR index_ < -1) THEN
        SIGNAL index_out_of_range SET MESSAGE_TEXT = "Index out of range";
    END IF;
    SET result = NULL;
    -- Use SUBSTRING_INDEX to split strings, which might be slow
    -- http://stackoverflow.com/questions/9814430
    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(`text`, "\n", index_ + 1), "\n", -1) INTO text_ FROM texts WHERE title = title_;
END//

