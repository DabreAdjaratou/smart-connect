ALTER TABLE `facility_details` CHANGE `vl_instance_id` `vl_instance_id` CHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

ALTER TABLE samples
DROP FOREIGN KEY samples_ibfk_2
ALTER TABLE samples
DROP FOREIGN KEY samples_ibfk_3

ALTER TABLE `samples` CHANGE `test_reason` `test_reason` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `lab_id` `lab_id` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `clinic_id` `clinic_id` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `sample_collection_date` `sample_collection_date` DATETIME NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `lab_tested_date` `lab_tested_date` DATETIME NULL DEFAULT NULL;
--saravanan 18-apr-2017
INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('sample_waiting_month_range', 'Sample Waiting Month Range', '3');

--Pal 03-July-2017
INSERT INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES
(2, 'lab user', 'lu', 'active'),
(3, 'clinic user', 'cu', 'active'),
(4, 'hub user', 'hu', 'active');

--Pal 04-July-2017
update dash_vl_request_form set sample_code= null where sample_code = 'NULL'

update dash_vl_request_form set sample_collection_date= null where sample_collection_date = 'NULL'

update dash_vl_request_form set patient_art_no= null where patient_art_no = 'NULL'

update dash_vl_request_form set patient_first_name= null where patient_first_name = 'NULL'

update dash_vl_request_form set patient_last_name= null where patient_last_name = 'NULL'

update dash_vl_request_form set patient_mobile_number= null where patient_mobile_number = 'NULL'

update dash_vl_request_form set patient_gender= null where patient_gender = 'NULL'

update dash_vl_request_form set request_clinician_name= null where request_clinician_name = 'NULL'

update dash_vl_request_form set sample_received_at_vl_lab_datetime= null where sample_received_at_vl_lab_datetime = 'NULL'

update dash_vl_request_form set sample_tested_datetime= null where sample_tested_datetime = 'NULL'

update dash_vl_request_form set vl_test_platform= null where vl_test_platform = 'NULL'

update dash_vl_request_form set result= null where result = 'NULL'

update dash_vl_request_form set result_value_log= null where result_value_log = 'NULL'

update dash_vl_request_form set result_value_absolute= null where result_value_absolute = 'NULL'

update dash_vl_request_form set result_value_text= null where result_value_text = 'NULL'

update dash_vl_request_form set result_value_absolute_decimal= null where result_value_absolute_decimal = 'NULL'

update dash_vl_request_form set approver_comments= null where approver_comments = 'NULL'

update dash_vl_request_form set last_viral_load_date= null where last_viral_load_date = 'NULL'

update dash_vl_request_form set last_viral_load_result= null where last_viral_load_result = 'NULL'

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('h_vl_msg', 'Result PDF High Viral Load Message', 'High Viral Load - need assessment for enhanced adherence or clinical assessment for possible switch to second line.'), ('l_vl_msg', 'Result PDF Low Viral Load Message', 'Viral load adequately controlled : continue current regimen');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('show_smiley', 'Do you want to show smiley at result pdf?', 'yes');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('header', 'Header', 'MINISTRY OF HEALTH');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('logo', 'Logo', NULL);

--Pal 05-July-2017
CREATE TABLE `dash_user_facility_map` (
  `map_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL
);
ALTER TABLE `dash_user_facility_map` ADD PRIMARY KEY(`map_id`);

ALTER TABLE `dash_user_facility_map` CHANGE `map_id` `map_id` INT(11) NOT NULL AUTO_INCREMENT;

  
alter table dash_user_facility_map add FOREIGN KEY(facility_id) REFERENCES facility_details(facility_id);

alter table dash_user_facility_map add FOREIGN KEY(user_id) REFERENCES dash_users(user_id);


-- Amit 07 July 2017

ALTER TABLE `dash_vl_request_form` ADD `line_of_treatment` VARCHAR(255) NULL AFTER `treatment_initiation`;


-- Amit 09 July 2017
ALTER TABLE `dash_vl_request_form` ADD `sample_reordered` VARCHAR(255) NULL DEFAULT 'no' AFTER `sample_code`;
ALTER TABLE `dash_vl_request_form` ADD INDEX(`patient_age_in_years `);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`lab_id`);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`sample_collection_date`);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`patient_gender`);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`sample_tested_datetime`);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`result`);
ALTER TABLE `dash_vl_request_form` ADD INDEX(`sample_type`);

--saravanan 10-July-2017
ALTER TABLE `r_vl_test_reasons` ADD `test_reason_code` VARCHAR(255) NULL DEFAULT NULL AFTER `test_reason_name`;

--Pal 26-July-2017
INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('announcement_msg', 'Announcement Message', NULL);

--Pal 28-July-2017
ALTER TABLE `facility_details` CHANGE `facility_state` `facility_state` INT(11) NULL DEFAULT NULL, CHANGE `facility_district` `facility_district` INT(11) NULL DEFAULT NULL

CREATE TABLE `location_details` (
  `location_id` int(11) NOT NULL,
  `parent_location` int(11) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `location_code` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL
)

ALTER TABLE `location_details`
  ADD PRIMARY KEY (`location_id`);
  
ALTER TABLE `location_details`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;
  
alter table location_details add FOREIGN key(parent_location) REFERENCES location_details(location_id)

ALTER TABLE `facility_details` CHANGE `facility_state` `facility_state` INT(11) NULL DEFAULT NULL, CHANGE `facility_district` `facility_district` INT(11) NULL DEFAULT NULL;

INSERT INTO `location_details` (`location_id`, `parent_location`, `location_name`, `location_code`, `latitude`, `longitude`) VALUES
(1, 0, 'Zambezia', NULL, NULL, NULL),
(2, 1, 'Quelimane', NULL, NULL, NULL),
(3, 1, 'Chinde', NULL, NULL, NULL),
(5, 1, 'Guru�', NULL, NULL, NULL),
(6, 1, 'Maganja da Costa', NULL, NULL, NULL),
(7, 1, 'Ile', NULL, NULL, NULL),
(8, 1, 'Pebane', NULL, NULL, NULL),
(9, 1, 'Inhassunge', NULL, NULL, NULL),
(10, 1, 'Gil�', NULL, NULL, NULL),
(11, 1, 'Mocubela', NULL, NULL, NULL),
(12, 1, 'Nicoadala', NULL, NULL, NULL),
(13, 1, 'Lugela', NULL, NULL, NULL),
(14, 1, 'Morrumbala', NULL, NULL, NULL),
(15, 1, 'Mulevala', NULL, NULL, NULL),
(16, 1, 'Milange', NULL, NULL, NULL),
(17, 1, 'Mocuba', NULL, NULL, NULL),
(18, 1, 'Alto Molocue', NULL, NULL, NULL),
(19, 1, 'Namacurra', NULL, NULL, NULL),
(20, 1, 'Namarroi', NULL, NULL, NULL),
(21, 1, 'Molumbo', NULL, NULL, NULL),
(22, 1, 'Mopeia', NULL, NULL, NULL),
(23, 0, 'Tete', NULL, NULL, NULL),
(24, 23, 'Ang�nia', NULL, NULL, NULL),
(25, 23, 'Cahora Bassa', NULL, NULL, NULL),
(26, 23, 'Changara', NULL, NULL, NULL),
(27, 23, 'Chifunde', NULL, NULL, NULL),
(28, 23, 'Chiuta', NULL, NULL, NULL),
(29, 23, 'Magoe', NULL, NULL, NULL),
(30, 23, 'Marara', NULL, NULL, NULL),
(31, 23, 'Mar via', NULL, NULL, NULL),
(32, 23, 'Moatize', NULL, NULL, NULL),
(33, 23, 'Mutarara', NULL, NULL, NULL),
(34, 23, 'Tete', NULL, NULL, NULL),
(35, 23, 'Zumbo', NULL, NULL, NULL),
(36, 0, 'Sofala', NULL, NULL, NULL),
(37, 36, 'Beira', NULL, NULL, NULL),
(38, 36, 'Buzi', NULL, NULL, NULL),
(39, 36, 'Caia', NULL, NULL, NULL),
(40, 36, 'Chemba', NULL, NULL, NULL),
(41, 36, 'Cheringoma', NULL, NULL, NULL),
(42, 36, 'Chibabava', NULL, NULL, NULL),
(43, 36, 'Dondo', NULL, NULL, NULL),
(44, 36, 'Gorongoza', NULL, NULL, NULL),
(45, 36, 'Machanga', NULL, NULL, NULL),
(46, 36, 'Maringue', NULL, NULL, NULL),
(47, 36, 'Marromeu', NULL, NULL, NULL),
(48, 36, 'Muanza', NULL, NULL, NULL),
(49, 36, 'Nhamatanda', NULL, NULL, NULL),
(50, 0, 'Niassa', NULL, NULL, NULL),
(51, 50, 'Chimbonila', NULL, NULL, NULL),
(52, 50, 'Cuamba', NULL, NULL, NULL),
(53, 50, 'Distrito de Lichinga', NULL, NULL, NULL),
(54, 50, 'Lago', NULL, NULL, NULL),
(55, 50, 'Mandimba', NULL, NULL, NULL),
(56, 50, 'Mecanhelas', NULL, NULL, NULL),
(57, 50, 'Ngauma', NULL, NULL, NULL),
(58, 50, 'Sanga', NULL, NULL, NULL),
(59, 0, 'Nampula', NULL, NULL, NULL),
(60, 59, 'Angoche', NULL, NULL, NULL),
(61, 59, 'Cidade de Nampula', NULL, NULL, NULL),
(62, 59, 'Erati', NULL, NULL, NULL),
(63, 59, 'Ilha de Mo�ambique', NULL, NULL, NULL),
(64, 59, 'Lalaua', NULL, NULL, NULL),
(65, 59, 'Larde', NULL, NULL, NULL),
(66, 59, 'Liupo', NULL, NULL, NULL),
(67, 59, 'Malema', NULL, NULL, NULL),
(68, 59, 'Meconta', NULL, NULL, NULL),
(69, 59, 'Mecuburi', NULL, NULL, NULL),
(70, 59, 'Memba', NULL, NULL, NULL),
(71, 59, 'Mogincual', NULL, NULL, NULL),
(72, 59, 'Mogovolas', NULL, NULL, NULL),
(73, 59, 'Moma', NULL, NULL, NULL),
(74, 59, 'Monapo', NULL, NULL, NULL),
(75, 59, 'Mossuril', NULL, NULL, NULL),
(76, 59, 'Muecate', NULL, NULL, NULL),
(77, 59, 'Murrupula', NULL, NULL, NULL),
(78, 59, 'Nacala Porto', NULL, NULL, NULL),
(79, 59, 'Nacala-a-velha', NULL, NULL, NULL),
(80, 59, 'Nacaroa', NULL, NULL, NULL),
(81, 59, 'Nampula', NULL, NULL, NULL),
(82, 59, 'Ribaue', NULL, NULL, NULL),
(83, 0, 'Maputo Provincia', NULL, NULL, NULL),
(84, 83, 'Boane', NULL, NULL, NULL),
(85, 83, 'Magude', NULL, NULL, NULL),
(86, 83, 'Manhi�a', NULL, NULL, NULL),
(87, 83, 'Marracuene', NULL, NULL, NULL),
(88, 83, 'Matola', NULL, NULL, NULL),
(89, 83, 'Matutine', NULL, NULL, NULL),
(90, 83, 'Moamba', NULL, NULL, NULL),
(91, 83, 'Namaacha', NULL, NULL, NULL),
(92, 0, 'Maputo Cidade', NULL, NULL, NULL),
(93, 92, 'Kamavota', NULL, NULL, NULL),
(94, 92, 'Kamaxakeni', NULL, NULL, NULL),
(95, 92, 'Kamubukwane', NULL, NULL, NULL),
(96, 92, 'Kanyaka', NULL, NULL, NULL),
(97, 92, 'Katembe', NULL, NULL, NULL),
(98, 92, 'Khampfumo', NULL, NULL, NULL),
(99, 92, 'Nlhamanculo', NULL, NULL, NULL),
(100, 0, 'Manica', NULL, NULL, NULL),
(101, 100, 'B rue', NULL, NULL, NULL),
(102, 100, 'Chimoio', NULL, NULL, NULL),
(103, 100, 'Gondola', NULL, NULL, NULL),
(104, 100, 'Manica', NULL, NULL, NULL),
(105, 100, 'Vanduzi', NULL, NULL, NULL),
(106, 0, 'Inhambane', NULL, NULL, NULL),
(107, 106, 'Funhalouro', NULL, NULL, NULL),
(108, 106, 'Govuro', NULL, NULL, NULL),
(109, 106, 'Inharrime', NULL, NULL, NULL),
(110, 106, 'Inhassoro', NULL, NULL, NULL),
(111, 106, 'Jangamo', NULL, NULL, NULL),
(112, 106, 'Mabote', NULL, NULL, NULL),
(113, 106, 'Maxixe', NULL, NULL, NULL),
(114, 106, 'Morrumbene', NULL, NULL, NULL),
(115, 106, 'Zavala', NULL, NULL, NULL),
(116, 0, 'Gaza', NULL, NULL, NULL),
(117, 116, 'Bilene', NULL, NULL, NULL),
(118, 116, 'Chibuto', NULL, NULL, NULL),
(119, 116, 'Chicualacuala', NULL, NULL, NULL),
(120, 116, 'Chigubo', NULL, NULL, NULL),
(121, 116, 'Chockwe', NULL, NULL, NULL),
(122, 116, 'Guj', NULL, NULL, NULL),
(123, 116, 'Mabalane', NULL, NULL, NULL),
(124, 116, 'Mandlakazi', NULL, NULL, NULL),
(125, 116, 'Massangena', NULL, NULL, NULL),
(126, 116, 'Massingir', NULL, NULL, NULL),
(127, 116, 'Xai-Xai', NULL, NULL, NULL),
(128, 116, 'Xai-Xai Distrito', NULL, NULL, NULL),
(129, 0, 'Cabo Delgado', NULL, NULL, NULL),
(130, 129, 'Ancuabe', NULL, NULL, NULL),
(131, 129, 'Chiure', NULL, NULL, NULL),
(132, 129, 'Macomia', NULL, NULL, NULL),
(133, 129, 'Mocimboa da Praia', NULL, NULL, NULL),
(134, 129, 'Montepuez', NULL, NULL, NULL),
(135, 129, 'Mueda', NULL, NULL, NULL),
(136, 129, 'Muidumbe', NULL, NULL, NULL),
(137, 129, 'Nangade', NULL, NULL, NULL),
(138, 129, 'Palma', NULL, NULL, NULL),
(139, 129, 'Pemba', NULL, NULL, NULL);

update facility_details set facility_state = NULL WHERE facility_state = 0

update facility_details set facility_district = NULL WHERE facility_district = 0


alter table facility_details add FOREIGN key(facility_state) REFERENCES location_details(location_id)
alter table facility_details add FOREIGN key(facility_district) REFERENCES location_details(location_id)

--saravanan 01-Aug-2017
ALTER TABLE `facility_details` ADD `facility_logo` VARCHAR(255) NULL DEFAULT NULL AFTER `status`;

--Pal 26-Aug-2017
CREATE TABLE `locale_details` (
  `locale_id` int(11) NOT NULL,
  `locale` varchar(45) NOT NULL,
  `display_name` varchar(45) NOT NULL,
  `locale_status` varchar(45) NOT NULL DEFAULT 'active'
)

INSERT INTO `locale_details` (`locale_id`, `locale`, `display_name`, `locale_status`) VALUES
(1, 'en_US', 'english', 'active'),
(2, 'pt_BR', 'portuguese', 'active');

ALTER TABLE `locale_details`
  ADD PRIMARY KEY (`locale_id`);
  
ALTER TABLE `locale_details`
  MODIFY `locale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
  
INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('language', 'Language', '1');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('watermark_text', 'Watermark Text', 'DEMO');

ALTER TABLE `dash_global_config` ADD `status` VARCHAR(45) NOT NULL DEFAULT 'active' AFTER `value`;

UPDATE `dash_global_config` SET `status` = 'inactive' WHERE `dash_global_config`.`name` = 'watermark_text'; 