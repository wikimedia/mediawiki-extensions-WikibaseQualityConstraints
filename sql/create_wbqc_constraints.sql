CREATE TABLE IF NOT EXISTS /*_*/wbqc_constraints (
  constraint_guid   		  VARBINARY(63)  	PRIMARY KEY,
  pid               		  INT(11)       	NOT NULL,
  constraint_type_qid    	VARBINARY(25)   NOT NULL,
  constraint_parameters		TEXT			      DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wbqc_constraints_pid_index
ON /*_*/wbqc_constraints (pid);
