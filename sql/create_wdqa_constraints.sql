CREATE TABLE IF NOT EXISTS /*_*/wdqa_constraints (
  constraint_guid   		  VARCHAR(255)  	PRIMARY KEY,
  pid               		  INT(11)       	NOT NULL,
  constraint_type_qid    	VARCHAR(255)  	NOT NULL,
  constraint_parameters		TEXT			      DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wdqa_constraints_pid_index
ON /*_*/wdqa_constraints (pid);

CREATE INDEX /*i*/wqda_constraints_constraint_type_qid_index
ON /*_*/wdqa_constraints (constraint_type_qid);