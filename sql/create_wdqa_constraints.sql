CREATE TABLE IF NOT EXISTS wdqa_constraints (
  constraint_guid   		  VARCHAR(255)  	PRIMARY KEY,
  pid               		  INT(11)       	NOT NULL,
  constraint_type_qid    	VARCHAR(255)  	NOT NULL,
  constraint_parameters		TEXT			      DEFAULT NULL
);

CREATE INDEX wdqa_constraints_pid_index
ON wdqa_constraints (pid);

CREATE INDEX wqda_constraints_constraint_type_qid_index
ON wdqa_constraints (constraint_type_qid);