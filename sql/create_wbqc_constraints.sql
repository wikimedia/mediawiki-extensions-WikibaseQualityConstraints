CREATE TABLE IF NOT EXISTS /*_*/wbqc_constraints (
  constraint_guid   		  VARCHAR(255)  	PRIMARY KEY,
  pid               		  INT(11)       	NOT NULL,
  constraint_type_qid    	VARCHAR(255)  	NOT NULL,
  constraint_parameters		TEXT			      DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wbqc_constraints_pid_index
ON /*_*/wbqc_constraints (pid);

CREATE INDEX /*i*/wbqc_constraints_constraint_type_qid_index
ON /*_*/wbqc_constraints (constraint_type_qid);