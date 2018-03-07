CREATE TABLE IF NOT EXISTS /*_*/wbqc_constraints (
  constraint_id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  constraint_guid varbinary(63) NOT NULL,
  pid int(11) NOT NULL,
  constraint_type_qid varbinary(25) NOT NULL,
  constraint_parameters text DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wbqc_constraints_pid_index ON /*_*/wbqc_constraints (pid);

CREATE INDEX /*i*/wbqc_constraints_guid_index ON /*_*/wbqc_constraints (constraint_guid);
