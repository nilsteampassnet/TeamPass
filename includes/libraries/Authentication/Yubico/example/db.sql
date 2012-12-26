drop table if exists demoserver; 
create table demoserver (
       id varchar(60) unique not null,
       username varchar(60) default '' not null,
       password varchar(60) default '' not null,

       primary key (id)
);
