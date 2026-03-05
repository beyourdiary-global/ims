-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 27, 2026 at 12:45 PM
-- Server version: 8.0.45
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beyourdi_cms-uat`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `log_action` int DEFAULT NULL,
  `screen_type` varchar(255) DEFAULT NULL,
  `query_record` text,
  `query_table` varchar(255) DEFAULT NULL,
  `old_value` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `new_value` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `changes` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `user_id` int DEFAULT NULL,
  `action_message` text,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `bank`
--

CREATE TABLE `bank` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `brand`
--

CREATE TABLE `brand` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `brand_series`
--

CREATE TABLE `brand_series` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `remark` text,
  `create_by` int NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int NOT NULL,
  `code` char(2) NOT NULL,
  `name` varchar(80) NOT NULL,
  `nicename` varchar(80) NOT NULL,
  `iso3` char(3) DEFAULT NULL,
  `numcode` smallint DEFAULT NULL,
  `phonecode` int NOT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `courier`
--

CREATE TABLE `courier` (
  `id` varchar(100) NOT NULL,
  `courierID` varchar(255) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `taxable` enum('Y','N') DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
  `tracking_link` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int NOT NULL,
  `default_currency_unit` int DEFAULT NULL,
  `exchange_currency_rate` double DEFAULT NULL,
  `exchange_currency_unit` int DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_unit`
--

CREATE TABLE `currency_unit` (
  `id` int NOT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `address_1` varchar(255) DEFAULT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `postcode` varchar(100) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `alt_contact` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `customer_facebook_deals_transaction`
--

CREATE TABLE `customer_facebook_deals_transaction` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `fb_link` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `sales_pic` int DEFAULT NULL,
  `country` int DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `fb_page` int DEFAULT NULL,
  `channel` int DEFAULT NULL,
  `series` int DEFAULT NULL,
  `ship_rec_name` varchar(100) DEFAULT NULL,
  `ship_rec_add` varchar(255) DEFAULT NULL,
  `ship_rec_contact` varchar(50) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_info`
--

CREATE TABLE `customer_info` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `phone_country` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `phone_number` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `gender` enum('Female','Male') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `birthday` date DEFAULT NULL,
  `shipping_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_last_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_address_1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_address_2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_city` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_state_province` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_zip_code` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_contact_number` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_country_region` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `shipping_company` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `default_segmentation` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tags` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `person_in_charges` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `create_by` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_lazada_deals_transaction`
--

CREATE TABLE `customer_lazada_deals_transaction` (
  `id` int NOT NULL,
  `lcr_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `sales_pic` varchar(100) NOT NULL,
  `country` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `ship_rec_name` varchar(100) NOT NULL,
  `ship_rec_add` varchar(255) NOT NULL,
  `ship_rec_contact` varchar(20) NOT NULL,
  `remark` text,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `customer_segmentation`
--

CREATE TABLE `customer_segmentation` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `colorCode` varchar(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
  `boxFrom` decimal(10,2) DEFAULT NULL,
  `boxUntil` decimal(10,2) DEFAULT NULL,
  `brandSeries` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `customer_website_deals_transaction`
--

CREATE TABLE `customer_website_deals_transaction` (
  `cust_id` varchar(255) DEFAULT NULL,
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `cust_email` varchar(255) DEFAULT NULL,
  `cust_birthday` date DEFAULT NULL,
  `sales_pic` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `ship_rec_name` varchar(255) DEFAULT NULL,
  `ship_rec_add` varchar(255) DEFAULT NULL,
  `ship_rec_contact` varchar(255) DEFAULT NULL,
  `remark` text,
  `create_by` int DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `designation`
--

CREATE TABLE `designation` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `employee_epf_rate`
--

CREATE TABLE `employee_epf_rate` (
  `id` int NOT NULL,
  `epf_rate` double DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `employee_info`
--

CREATE TABLE `employee_info` (
  `id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `join_date` date NOT NULL,
  `position` varchar(255) NOT NULL,
  `employment_status_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `salary_frequency` enum('monthly','daily','hourly') NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `currency_unit_id` int DEFAULT NULL,
  `allowance` decimal(10,2) DEFAULT NULL,
  `managers_for_leave_approval` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `contributing_epf` enum('Yes','No') NOT NULL,
  `contributing_epf_no` varchar(20) DEFAULT NULL,
  `employee_epf_rate_id` int DEFAULT NULL,
  `employer_epf_rate_id` int DEFAULT NULL,
  `employee_tax_number` varchar(20) DEFAULT NULL,
  `socso_category_id` int DEFAULT NULL,
  `eis` enum('Yes','No') DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave`
--

CREATE TABLE `employee_leave` (
  `id` int NOT NULL,
  `employeeID` int NOT NULL,
  `leaveType_1` int DEFAULT NULL,
  `leaveType_8` int DEFAULT NULL,
  `leaveType_2` int DEFAULT NULL,
  `leaveType_3` int DEFAULT NULL,
  `leaveType_5` int DEFAULT NULL,
  `leaveType_7` int DEFAULT NULL,
  `create_by` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_personal_info`
--

CREATE TABLE `employee_personal_info` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `id_type` int DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `gender` enum('Female','Male') NOT NULL,
  `date_of_birth` date NOT NULL,
  `residence_status` enum('Resident','Non-Resident') NOT NULL,
  `nationality` int DEFAULT NULL,
  `marital_status` int DEFAULT NULL,
  `no_of_children` int DEFAULT NULL,
  `race_id` int DEFAULT NULL,
  `address_line_1` varchar(255) DEFAULT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `alternate_phone_number` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(255) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `emergency_relationship` varchar(255) NOT NULL,
  `preferred_payment_method` int DEFAULT NULL,
  `bank_id` int DEFAULT NULL,
  `account_holders_name` varchar(255) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `employer_epf_rate`
--

CREATE TABLE `employer_epf_rate` (
  `id` int NOT NULL,
  `epf_rate` double DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `em_type_status`
--

CREATE TABLE `em_type_status` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `expense_type`
--

CREATE TABLE `expense_type` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holiday`
--

CREATE TABLE `holiday` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `identity_type`
--

CREATE TABLE `identity_type` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `language`
--

CREATE TABLE `language` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'language',
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `lazada_order_request`
--

CREATE TABLE `lazada_order_request` (
  `id` int NOT NULL,
  `lazada_acc` varchar(255) DEFAULT NULL,
  `curr_unit` varchar(255) DEFAULT NULL,
  `lzd_country` varchar(255) DEFAULT NULL,
  `cust_id` varchar(255) DEFAULT NULL,
  `cust_name` varchar(255) DEFAULT NULL,
  `cust_email` varchar(255) DEFAULT NULL,
  `cust_phone` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `oder_number` varchar(255) DEFAULT NULL,
  `sales_pic` varchar(255) DEFAULT NULL,
  `ship_rec_name` varchar(255) DEFAULT NULL,
  `ship_rec_address` varchar(255) DEFAULT NULL,
  `ship_rec_contact` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `pkg` varchar(255) DEFAULT NULL,
  `item_price_credit` decimal(10,2) DEFAULT NULL,
  `commision` decimal(10,2) DEFAULT NULL,
  `other_discount` decimal(10,2) DEFAULT NULL,
  `pay_fee` decimal(10,2) DEFAULT NULL,
  `final_income` decimal(10,2) DEFAULT NULL,
  `pay_meth` varchar(255) DEFAULT NULL,
  `remark` text,
  `create_by` int DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
  `order_status` char(10) NOT NULL DEFAULT 'P'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `leave_pending`
--

CREATE TABLE `leave_pending` (
  `id` int NOT NULL,
  `applicant` int NOT NULL,
  `leave_type` int NOT NULL,
  `from_time` datetime NOT NULL,
  `to_time` datetime NOT NULL,
  `numOfdays` decimal(10,2) NOT NULL,
  `remainingLeave` decimal(10,2) NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `pending_approver` varchar(50) DEFAULT NULL,
  `success_approver` varchar(50) DEFAULT NULL,
  `reject_approver` varchar(50) DEFAULT NULL,
  `leave_transaction_status` enum('pending','approval','declined','cancel') DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `leave_status`
--

CREATE TABLE `leave_status` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `leave_type`
--

CREATE TABLE `leave_type` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `num_of_days` varchar(100) DEFAULT NULL,
  `leave_status` varchar(100) DEFAULT NULL,
  `auto_assign` enum('yes','no') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no',
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `marital_status`
--

CREATE TABLE `marital_status` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `official_process_order`
--

CREATE TABLE `official_process_order` (
  `id` int NOT NULL,
  `official_order_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `courier_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tracking_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `channel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package`
--

CREATE TABLE `package` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `cost_curr` int DEFAULT NULL,
  `agent_cost` int NOT NULL,
  `price` double(10,2) DEFAULT NULL,
  `currency_unit` varchar(100) DEFAULT NULL,
  `product` varchar(100) DEFAULT NULL,
  `barcode_slot_total` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `payment_method`
--

CREATE TABLE `payment_method` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `installment_period` int NOT NULL DEFAULT '0',
  `service_rate` double NOT NULL DEFAULT '0',
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pin`
--

CREATE TABLE `pin` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pin_group`
--

CREATE TABLE `pin_group` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `pins` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `platform`
--

CREATE TABLE `platform` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `weight` varchar(100) DEFAULT NULL,
  `weight_unit` varchar(100) DEFAULT NULL,
  `cost` varchar(100) DEFAULT NULL,
  `currency_unit` varchar(100) DEFAULT NULL,
  `barcode_status` varchar(100) DEFAULT NULL,
  `barcode_slot` varchar(100) DEFAULT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `parent_product` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `product_category`
--

CREATE TABLE `product_category` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `product_status`
--

CREATE TABLE `product_status` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `project_title` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `company_contact` varchar(100) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_business_no` varchar(255) DEFAULT NULL,
  `meta` text,
  `logo` varchar(255) DEFAULT NULL,
  `meta_logo` varchar(255) DEFAULT NULL,
  `themesColor` varchar(10) NOT NULL,
  `buttonColor` varchar(10) NOT NULL,
  `finance_year` date DEFAULT NULL,
  `barcode_prefix` varchar(255) DEFAULT NULL,
  `barcode_next_number` varchar(11) DEFAULT NULL,
  `barcode_date_time` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'Y' COMMENT 'Y/N',
  `barcode_encode` varchar(1) DEFAULT 'Y' COMMENT 'Y/N',
  `invoice_prefix_credit` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `invoice_next_number_credit` int DEFAULT NULL,
  `invoice_prefix_debit` varchar(255) DEFAULT NULL,
  `invoice_next_number_debit` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `race`
--

CREATE TABLE `race` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_request`
--

CREATE TABLE `shipping_request` (
  `id` int NOT NULL,
  `order_no` varchar(100) NOT NULL,
  `customer_id` varchar(100) NOT NULL,
  `courier_id` varchar(100) NOT NULL,
  `awb` varchar(100) NOT NULL,
  `shipping_cost` varchar(100) NOT NULL,
  `currency_unit` varchar(100) NOT NULL,
  `shipping_type_id` varchar(100) NOT NULL,
  `parcel_content` varchar(255) NOT NULL,
  `parcel_value` varchar(100) NOT NULL,
  `weight` varchar(100) NOT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `shorten`
--

CREATE TABLE `shorten` (
  `id` int NOT NULL,
  `shorten_key` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `image1` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `image2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `image3` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `image4` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `destination_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `socso_category`
--

CREATE TABLE `socso_category` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stock_record`
--

CREATE TABLE `stock_record` (
  `id` int NOT NULL,
  `brand_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `stock_in_date` date DEFAULT NULL,
  `stock_out_date` date DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `product_batch_code` varchar(255) DEFAULT NULL,
  `product_status_id` int DEFAULT NULL,
  `product_category_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `stock_in_person_in_charges` varchar(255) DEFAULT NULL,
  `stock_out_person_in_charges` varchar(255) DEFAULT NULL,
  `stock_out_customer_purchase_id` int DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `status` varchar(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tag`
--

CREATE TABLE `tag` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `urbanism_customer_register_info`
--

CREATE TABLE `urbanism_customer_register_info` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `ic` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `reg_date` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `password_alt` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `access_id` int DEFAULT NULL COMMENT 'user group level',
  `employee_id` int NOT NULL COMMENT 'get from employee info ID',
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `fail_count` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_group`
--

CREATE TABLE `user_group` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `pins` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse`
--

CREATE TABLE `warehouse` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `weight_unit`
--

CREATE TABLE `weight_unit` (
  `id` int NOT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `create_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `yearly_goals`
--

CREATE TABLE `yearly_goals` (
  `id` int NOT NULL,
  `year` int NOT NULL,
  `month` varchar(3) NOT NULL,
  `shopee_my_goal` decimal(10,2) DEFAULT '0.00',
  `shopee_sg_goal` decimal(10,2) DEFAULT '0.00',
  `lazada_goal` decimal(10,2) DEFAULT '0.00',
  `facebook_goal` decimal(10,2) DEFAULT '0.00',
  `website_goal` decimal(10,2) DEFAULT '0.00',
  `total_goal` decimal(10,2) GENERATED ALWAYS AS (((((`shopee_my_goal` + `shopee_sg_goal`) + `lazada_goal`) + `facebook_goal`) + `website_goal`)) STORED,
  `status` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bank`
--
ALTER TABLE `bank`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brand`
--
ALTER TABLE `brand`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brand_series`
--
ALTER TABLE `brand_series`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courier`
--
ALTER TABLE `courier`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `currency_unit`
--
ALTER TABLE `currency_unit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_facebook_deals_transaction`
--
ALTER TABLE `customer_facebook_deals_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_info`
--
ALTER TABLE `customer_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_lazada_deals_transaction`
--
ALTER TABLE `customer_lazada_deals_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_segmentation`
--
ALTER TABLE `customer_segmentation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_website_deals_transaction`
--
ALTER TABLE `customer_website_deals_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `designation`
--
ALTER TABLE `designation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_epf_rate`
--
ALTER TABLE `employee_epf_rate`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_info`
--
ALTER TABLE `employee_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_leave`
--
ALTER TABLE `employee_leave`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_personal_info`
--
ALTER TABLE `employee_personal_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employer_epf_rate`
--
ALTER TABLE `employer_epf_rate`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `em_type_status`
--
ALTER TABLE `em_type_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_type`
--
ALTER TABLE `expense_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holiday`
--
ALTER TABLE `holiday`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `identity_type`
--
ALTER TABLE `identity_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `language`
--
ALTER TABLE `language`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lazada_order_request`
--
ALTER TABLE `lazada_order_request`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_pending`
--
ALTER TABLE `leave_pending`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_status`
--
ALTER TABLE `leave_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_type`
--
ALTER TABLE `leave_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marital_status`
--
ALTER TABLE `marital_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `official_process_order`
--
ALTER TABLE `official_process_order`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `package`
--
ALTER TABLE `package`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_method`
--
ALTER TABLE `payment_method`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pin`
--
ALTER TABLE `pin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pin_group`
--
ALTER TABLE `pin_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `platform`
--
ALTER TABLE `platform`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_category`
--
ALTER TABLE `product_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_status`
--
ALTER TABLE `product_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `race`
--
ALTER TABLE `race`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shipping_request`
--
ALTER TABLE `shipping_request`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shorten`
--
ALTER TABLE `shorten`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shorten_key` (`shorten_key`);

--
-- Indexes for table `socso_category`
--
ALTER TABLE `socso_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_record`
--
ALTER TABLE `stock_record`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tag`
--
ALTER TABLE `tag`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `urbanism_customer_register_info`
--
ALTER TABLE `urbanism_customer_register_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_group`
--
ALTER TABLE `user_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `weight_unit`
--
ALTER TABLE `weight_unit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `yearly_goals`
--
ALTER TABLE `yearly_goals`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank`
--
ALTER TABLE `bank`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brand`
--
ALTER TABLE `brand`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brand_series`
--
ALTER TABLE `brand_series`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_unit`
--
ALTER TABLE `currency_unit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_facebook_deals_transaction`
--
ALTER TABLE `customer_facebook_deals_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_info`
--
ALTER TABLE `customer_info`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_lazada_deals_transaction`
--
ALTER TABLE `customer_lazada_deals_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_segmentation`
--
ALTER TABLE `customer_segmentation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_website_deals_transaction`
--
ALTER TABLE `customer_website_deals_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designation`
--
ALTER TABLE `designation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_epf_rate`
--
ALTER TABLE `employee_epf_rate`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_info`
--
ALTER TABLE `employee_info`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_leave`
--
ALTER TABLE `employee_leave`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_personal_info`
--
ALTER TABLE `employee_personal_info`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employer_epf_rate`
--
ALTER TABLE `employer_epf_rate`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `em_type_status`
--
ALTER TABLE `em_type_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_type`
--
ALTER TABLE `expense_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holiday`
--
ALTER TABLE `holiday`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `identity_type`
--
ALTER TABLE `identity_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lazada_order_request`
--
ALTER TABLE `lazada_order_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_pending`
--
ALTER TABLE `leave_pending`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_status`
--
ALTER TABLE `leave_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_type`
--
ALTER TABLE `leave_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marital_status`
--
ALTER TABLE `marital_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `official_process_order`
--
ALTER TABLE `official_process_order`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package`
--
ALTER TABLE `package`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_method`
--
ALTER TABLE `payment_method`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pin`
--
ALTER TABLE `pin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pin_group`
--
ALTER TABLE `pin_group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform`
--
ALTER TABLE `platform`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_category`
--
ALTER TABLE `product_category`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_status`
--
ALTER TABLE `product_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `race`
--
ALTER TABLE `race`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipping_request`
--
ALTER TABLE `shipping_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shorten`
--
ALTER TABLE `shorten`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `socso_category`
--
ALTER TABLE `socso_category`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_record`
--
ALTER TABLE `stock_record`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tag`
--
ALTER TABLE `tag`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `urbanism_customer_register_info`
--
ALTER TABLE `urbanism_customer_register_info`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_group`
--
ALTER TABLE `user_group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weight_unit`
--
ALTER TABLE `weight_unit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `yearly_goals`
--
ALTER TABLE `yearly_goals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
