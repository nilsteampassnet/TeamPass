DROP TABLE teampass_automatic_del;

CREATE TABLE `teampass_automatic_del` (
  `item_id` int(11) NOT NULL,
  `del_enabled` tinyint(1) NOT NULL,
  `del_type` tinyint(1) NOT NULL,
  `del_value` varchar(35) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




