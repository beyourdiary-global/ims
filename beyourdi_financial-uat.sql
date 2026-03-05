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
-- Database: `beyourdi_financial-uat`
--

-- --------------------------------------------------------

--
-- Table structure for table `agent`
--

CREATE TABLE `agent` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `brand` int DEFAULT NULL,
  `pic` int NOT NULL,
  `contact` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `country` int DEFAULT NULL,
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `create_by` int DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_cash_on_hand_transaction`
--

CREATE TABLE `asset_cash_on_hand_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) DEFAULT NULL,
  `type` enum('Add','Deduct') DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `bank` int DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `prev_amt` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `asset_current_bank_acc_transaction`
--

CREATE TABLE `asset_current_bank_acc_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) DEFAULT NULL,
  `type` enum('Add','Deduct') DEFAULT NULL,
  `date` date DEFAULT NULL,
  `bank` int DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `prev_amt` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_initial_capital_transaction`
--

CREATE TABLE `asset_initial_capital_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_inventories_transaction`
--

CREATE TABLE `asset_inventories_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `merchantID` int DEFAULT NULL,
  `itemID` int DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `bal_qty` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
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
-- Table structure for table `asset_investment_transaction`
--

CREATE TABLE `asset_investment_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) DEFAULT NULL,
  `type` enum('Add','Deduct') DEFAULT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `prev_amt` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `merchant` int DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
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
-- Table structure for table `asset_other_creditor_transaction`
--

CREATE TABLE `asset_other_creditor_transaction` (
  `id` int NOT NULL,
  `transactionID` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `type` enum('Add','Deduct') DEFAULT NULL,
  `date` date DEFAULT NULL,
  `creditor` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `prev_amt` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_sundry_debtors_transactions`
--

CREATE TABLE `asset_sundry_debtors_transactions` (
  `id` int NOT NULL,
  `transactionID` varchar(255) DEFAULT NULL,
  `type` enum('Add','Deduct') DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `debtors` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `prev_amt` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `atome_transaction_backup`
--

CREATE TABLE `atome_transaction_backup` (
  `id` int NOT NULL,
  `trans_id` varchar(255) NOT NULL,
  `atome_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `trans_outlet` varchar(255) NOT NULL,
  `platform_id` varchar(255) NOT NULL,
  `amt_rec` decimal(10,2) NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `bank_transaction_backup`
--

CREATE TABLE `bank_transaction_backup` (
  `id` int NOT NULL,
  `year` int DEFAULT NULL,
  `month` int DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chanel_social_media`
--

CREATE TABLE `chanel_social_media` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
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
-- Table structure for table `credit_notes_invoice`
--

CREATE TABLE `credit_notes_invoice` (
  `id` int NOT NULL,
  `projectID` int DEFAULT NULL,
  `invoice` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `bill_nameID` int DEFAULT NULL,
  `bill_add` varchar(255) DEFAULT NULL,
  `bill_email` varchar(100) DEFAULT NULL,
  `bill_contact` varchar(50) DEFAULT NULL,
  `products` varchar(50) DEFAULT NULL,
  `pay_method` int DEFAULT NULL,
  `pay_details` varchar(255) DEFAULT NULL,
  `pay_terms` int DEFAULT NULL,
  `sales_pic` int DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `inv_note` varchar(255) DEFAULT NULL,
  `payment_status` varchar(100) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date NOT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cred_inv_products`
--

CREATE TABLE `cred_inv_products` (
  `id` int NOT NULL,
  `invoice_row` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debit_inv_products`
--

CREATE TABLE `debit_inv_products` (
  `id` int NOT NULL,
  `invoice_row` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debit_notes_invoice`
--

CREATE TABLE `debit_notes_invoice` (
  `id` int NOT NULL,
  `projectID` int DEFAULT NULL,
  `invoice` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `bill_nameID` int DEFAULT NULL,
  `bill_add` varchar(255) DEFAULT NULL,
  `bill_email` varchar(100) DEFAULT NULL,
  `bill_contact` varchar(50) DEFAULT NULL,
  `products` varchar(50) DEFAULT NULL,
  `pay_method` int DEFAULT NULL,
  `pay_details` varchar(255) DEFAULT NULL,
  `pay_terms` int DEFAULT NULL,
  `sales_pic` int DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `inv_note` varchar(255) DEFAULT NULL,
  `payment_status` varchar(100) DEFAULT NULL,
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
-- Table structure for table `delivery_fees_claim_transaction`
--

CREATE TABLE `delivery_fees_claim_transaction` (
  `id` int NOT NULL,
  `courier` varchar(255) DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `downline_top_up_record`
--

CREATE TABLE `downline_top_up_record` (
  `id` int NOT NULL,
  `agent` int DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `currency_unit` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `facebook_ads_topup_transaction`
--

CREATE TABLE `facebook_ads_topup_transaction` (
  `id` int NOT NULL,
  `meta_acc` int NOT NULL,
  `transactionID` varchar(255) NOT NULL,
  `payment_date` date NOT NULL,
  `pic` int NOT NULL,
  `topup_amt` decimal(10,2) NOT NULL,
  `attachment` varchar(255) NOT NULL,
  `remark` varchar(255) NOT NULL,
  `create_by` varchar(100) NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` varchar(100) NOT NULL,
  `update_date` date NOT NULL,
  `update_time` time NOT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_order_request`
--

CREATE TABLE `facebook_order_request` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `fb_link` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `sales_pic` int DEFAULT NULL,
  `country` int DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `series` int DEFAULT NULL,
  `package` int DEFAULT NULL,
  `barcode_slot` varchar(255) DEFAULT NULL,
  `fb_page` int DEFAULT NULL,
  `channel` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `pay_method` int DEFAULT NULL,
  `ship_rec_name` varchar(100) DEFAULT NULL,
  `ship_rec_add` varchar(255) DEFAULT NULL,
  `ship_rec_contact` varchar(50) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'A',
  `order_status` char(10) NOT NULL DEFAULT 'P'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_page_account`
--

CREATE TABLE `facebook_page_account` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_bin,
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
-- Table structure for table `finance_payment_method`
--

CREATE TABLE `finance_payment_method` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` varchar(100) NOT NULL,
  `update_date` date NOT NULL,
  `update_time` time NOT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internal_consume_item`
--

CREATE TABLE `internal_consume_item` (
  `id` int NOT NULL,
  `date` date NOT NULL,
  `pic` int NOT NULL,
  `brand` int NOT NULL,
  `package` int NOT NULL,
  `cost` decimal(10,2) NOT NULL,
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
-- Table structure for table `internal_consume_ticket_credit_transaction`
--

CREATE TABLE `internal_consume_ticket_credit_transaction` (
  `id` int NOT NULL,
  `date` date DEFAULT NULL,
  `PIC` int DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `currency_unit` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jt_transaction_backup`
--

CREATE TABLE `jt_transaction_backup` (
  `id` int NOT NULL,
  `number` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `lazada_account`
--

CREATE TABLE `lazada_account` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `currency_unit` varchar(255) NOT NULL,
  `create_by` varchar(255) NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `merchant`
--

CREATE TABLE `merchant` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `business_no` varchar(100) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `person_in_charges` char(100) DEFAULT NULL,
  `person_in_charges_contact` varchar(100) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `merchant_commission`
--

CREATE TABLE `merchant_commission` (
  `id` int NOT NULL,
  `merchantID` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `currency` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `remark` varchar(255) NOT NULL,
  `create_by` varchar(100) NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` varchar(100) NOT NULL,
  `update_date` date NOT NULL,
  `update_time` time NOT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meta_ads_account`
--

CREATE TABLE `meta_ads_account` (
  `id` int NOT NULL,
  `accID` varchar(255) DEFAULT NULL,
  `accName` varchar(255) DEFAULT NULL,
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
-- Table structure for table `payment_terms`
--

CREATE TABLE `payment_terms` (
  `id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `create_by` varchar(100) DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shopee_account`
--

CREATE TABLE `shopee_account` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `country` int DEFAULT NULL,
  `currency_unit` varchar(255) NOT NULL,
  `create_by` varchar(255) NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` varchar(255) DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `shopee_ads_topup_transaction`
--

CREATE TABLE `shopee_ads_topup_transaction` (
  `id` int NOT NULL,
  `shopee_acc` int DEFAULT NULL,
  `orderID` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `topup_amt` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `gst` decimal(10,2) DEFAULT NULL,
  `pay_meth` int DEFAULT NULL,
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
-- Table structure for table `shopee_customer_info`
--

CREATE TABLE `shopee_customer_info` (
  `id` int NOT NULL,
  `buyer_username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `country` int DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `series` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shopee_payment_method`
--

CREATE TABLE `shopee_payment_method` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `fees` decimal(10,2) NOT NULL,
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
-- Table structure for table `shopee_service_charges_rate_setting`
--

CREATE TABLE `shopee_service_charges_rate_setting` (
  `id` int NOT NULL,
  `currency_unit` int NOT NULL,
  `commission` decimal(10,2) NOT NULL,
  `service` decimal(10,2) NOT NULL,
  `transaction` decimal(10,2) NOT NULL,
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
-- Table structure for table `shopee_sg_fees_setting`
--

CREATE TABLE `shopee_sg_fees_setting` (
  `id` int NOT NULL,
  `commission` decimal(10,2) NOT NULL,
  `service` decimal(10,2) NOT NULL,
  `transaction` decimal(10,2) NOT NULL,
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
-- Table structure for table `shopee_sg_order_request`
--

CREATE TABLE `shopee_sg_order_request` (
  `id` int NOT NULL,
  `shopee_acc` int DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `orderID` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `package` int DEFAULT NULL,
  `barcode_slot` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `buyer` int DEFAULT NULL,
  `buyer_pay_meth` int DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `voucher` decimal(10,2) DEFAULT NULL,
  `act_shipping_fee` decimal(10,2) DEFAULT NULL,
  `service_fee` decimal(10,2) DEFAULT NULL,
  `trans_fee` decimal(10,2) DEFAULT NULL,
  `ams_fee` decimal(10,2) DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'A',
  `order_status` char(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'P'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shopee_sg_order_request-bk16042025`
--

CREATE TABLE `shopee_sg_order_request-bk16042025` (
  `id` int NOT NULL,
  `shopee_acc` int DEFAULT NULL,
  `currency` int DEFAULT NULL,
  `orderID` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `package` int DEFAULT NULL,
  `barcode_slot` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `brand` int DEFAULT NULL,
  `buyer` int DEFAULT NULL,
  `buyer_pay_meth` int DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `voucher` decimal(10,2) DEFAULT NULL,
  `act_shipping_fee` decimal(10,2) DEFAULT NULL,
  `service_fee` decimal(10,2) DEFAULT NULL,
  `trans_fee` decimal(10,2) DEFAULT NULL,
  `ams_fee` decimal(10,2) DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT NULL,
  `final_amt` decimal(10,2) DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `create_date` date DEFAULT NULL,
  `create_time` time DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'A',
  `order_status` char(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'P'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shopee_withdrawal_transactions`
--

CREATE TABLE `shopee_withdrawal_transactions` (
  `id` int NOT NULL,
  `date` date NOT NULL,
  `swt_id` varchar(255) NOT NULL,
  `currency_unit` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `pic` int NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `stock_credit_topup_record`
--

CREATE TABLE `stock_credit_topup_record` (
  `id` int NOT NULL,
  `merchant` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `currency_unit` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `stripe_transaction_backup`
--

CREATE TABLE `stripe_transaction_backup` (
  `id` int NOT NULL,
  `payout_id` int DEFAULT NULL,
  `date_paid` date DEFAULT NULL,
  `curr_unit` varchar(10) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
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
-- Table structure for table `tax_setting`
--

CREATE TABLE `tax_setting` (
  `id` int NOT NULL,
  `country` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `create_by` int NOT NULL,
  `create_date` date NOT NULL,
  `create_time` time NOT NULL,
  `update_by` int DEFAULT NULL,
  `update_date` date DEFAULT NULL,
  `update_time` time DEFAULT NULL,
  `status` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `website_order_request`
--

CREATE TABLE `website_order_request` (
  `id` int NOT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `pkg` varchar(255) DEFAULT NULL,
  `barcode_slot` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `shipping` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `pay_method` varchar(255) DEFAULT NULL,
  `pic` varchar(255) DEFAULT NULL,
  `cust_id` varchar(255) DEFAULT NULL,
  `cust_name` varchar(255) DEFAULT NULL,
  `cust_email` varchar(255) DEFAULT NULL,
  `cust_birthday` date DEFAULT NULL,
  `shipping_name` varchar(255) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `shipping_contact` varchar(255) DEFAULT NULL,
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agent`
--
ALTER TABLE `agent`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_cash_on_hand_transaction`
--
ALTER TABLE `asset_cash_on_hand_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_current_bank_acc_transaction`
--
ALTER TABLE `asset_current_bank_acc_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_initial_capital_transaction`
--
ALTER TABLE `asset_initial_capital_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_inventories_transaction`
--
ALTER TABLE `asset_inventories_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_investment_transaction`
--
ALTER TABLE `asset_investment_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_other_creditor_transaction`
--
ALTER TABLE `asset_other_creditor_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_sundry_debtors_transactions`
--
ALTER TABLE `asset_sundry_debtors_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atome_transaction_backup`
--
ALTER TABLE `atome_transaction_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bank_transaction_backup`
--
ALTER TABLE `bank_transaction_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chanel_social_media`
--
ALTER TABLE `chanel_social_media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `credit_notes_invoice`
--
ALTER TABLE `credit_notes_invoice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cred_inv_products`
--
ALTER TABLE `cred_inv_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_row` (`invoice_row`);

--
-- Indexes for table `debit_inv_products`
--
ALTER TABLE `debit_inv_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `debit_notes_invoice`
--
ALTER TABLE `debit_notes_invoice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_fees_claim_transaction`
--
ALTER TABLE `delivery_fees_claim_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `downline_top_up_record`
--
ALTER TABLE `downline_top_up_record`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_type`
--
ALTER TABLE `expense_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facebook_ads_topup_transaction`
--
ALTER TABLE `facebook_ads_topup_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facebook_order_request`
--
ALTER TABLE `facebook_order_request`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facebook_page_account`
--
ALTER TABLE `facebook_page_account`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `finance_payment_method`
--
ALTER TABLE `finance_payment_method`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `internal_consume_item`
--
ALTER TABLE `internal_consume_item`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `internal_consume_ticket_credit_transaction`
--
ALTER TABLE `internal_consume_ticket_credit_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jt_transaction_backup`
--
ALTER TABLE `jt_transaction_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lazada_account`
--
ALTER TABLE `lazada_account`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merchant`
--
ALTER TABLE `merchant`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merchant_commission`
--
ALTER TABLE `merchant_commission`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meta_ads_account`
--
ALTER TABLE `meta_ads_account`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_account`
--
ALTER TABLE `shopee_account`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_ads_topup_transaction`
--
ALTER TABLE `shopee_ads_topup_transaction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_customer_info`
--
ALTER TABLE `shopee_customer_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_payment_method`
--
ALTER TABLE `shopee_payment_method`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_service_charges_rate_setting`
--
ALTER TABLE `shopee_service_charges_rate_setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_sg_fees_setting`
--
ALTER TABLE `shopee_sg_fees_setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_sg_order_request`
--
ALTER TABLE `shopee_sg_order_request`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_sg_order_request-bk16042025`
--
ALTER TABLE `shopee_sg_order_request-bk16042025`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shopee_withdrawal_transactions`
--
ALTER TABLE `shopee_withdrawal_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_credit_topup_record`
--
ALTER TABLE `stock_credit_topup_record`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stripe_transaction_backup`
--
ALTER TABLE `stripe_transaction_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tax_setting`
--
ALTER TABLE `tax_setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `website_order_request`
--
ALTER TABLE `website_order_request`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agent`
--
ALTER TABLE `agent`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_cash_on_hand_transaction`
--
ALTER TABLE `asset_cash_on_hand_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_current_bank_acc_transaction`
--
ALTER TABLE `asset_current_bank_acc_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_initial_capital_transaction`
--
ALTER TABLE `asset_initial_capital_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_inventories_transaction`
--
ALTER TABLE `asset_inventories_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_investment_transaction`
--
ALTER TABLE `asset_investment_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_other_creditor_transaction`
--
ALTER TABLE `asset_other_creditor_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_sundry_debtors_transactions`
--
ALTER TABLE `asset_sundry_debtors_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atome_transaction_backup`
--
ALTER TABLE `atome_transaction_backup`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_transaction_backup`
--
ALTER TABLE `bank_transaction_backup`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chanel_social_media`
--
ALTER TABLE `chanel_social_media`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_notes_invoice`
--
ALTER TABLE `credit_notes_invoice`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cred_inv_products`
--
ALTER TABLE `cred_inv_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debit_inv_products`
--
ALTER TABLE `debit_inv_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debit_notes_invoice`
--
ALTER TABLE `debit_notes_invoice`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_fees_claim_transaction`
--
ALTER TABLE `delivery_fees_claim_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `downline_top_up_record`
--
ALTER TABLE `downline_top_up_record`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_type`
--
ALTER TABLE `expense_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facebook_ads_topup_transaction`
--
ALTER TABLE `facebook_ads_topup_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facebook_order_request`
--
ALTER TABLE `facebook_order_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facebook_page_account`
--
ALTER TABLE `facebook_page_account`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_payment_method`
--
ALTER TABLE `finance_payment_method`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internal_consume_item`
--
ALTER TABLE `internal_consume_item`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internal_consume_ticket_credit_transaction`
--
ALTER TABLE `internal_consume_ticket_credit_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jt_transaction_backup`
--
ALTER TABLE `jt_transaction_backup`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lazada_account`
--
ALTER TABLE `lazada_account`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant`
--
ALTER TABLE `merchant`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant_commission`
--
ALTER TABLE `merchant_commission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meta_ads_account`
--
ALTER TABLE `meta_ads_account`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_terms`
--
ALTER TABLE `payment_terms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_account`
--
ALTER TABLE `shopee_account`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_ads_topup_transaction`
--
ALTER TABLE `shopee_ads_topup_transaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_customer_info`
--
ALTER TABLE `shopee_customer_info`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_payment_method`
--
ALTER TABLE `shopee_payment_method`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_service_charges_rate_setting`
--
ALTER TABLE `shopee_service_charges_rate_setting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_sg_fees_setting`
--
ALTER TABLE `shopee_sg_fees_setting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_sg_order_request`
--
ALTER TABLE `shopee_sg_order_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_sg_order_request-bk16042025`
--
ALTER TABLE `shopee_sg_order_request-bk16042025`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopee_withdrawal_transactions`
--
ALTER TABLE `shopee_withdrawal_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_credit_topup_record`
--
ALTER TABLE `stock_credit_topup_record`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stripe_transaction_backup`
--
ALTER TABLE `stripe_transaction_backup`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_setting`
--
ALTER TABLE `tax_setting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `website_order_request`
--
ALTER TABLE `website_order_request`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
