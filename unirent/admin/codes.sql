-- Optimized trigger with conditional logging
CREATE TRIGGER tr_equipment_selective_audit 
AFTER UPDATE ON equipment 
FOR EACH ROW 
BEGIN
    -- Only log significant changes
    IF (OLD.daily_rate != NEW.daily_rate OR 
        OLD.status != NEW.status OR 
        OLD.owner_id != NEW.owner_id) THEN
        INSERT INTO audit_equipment (...) VALUES (...);
    END IF;
END;
