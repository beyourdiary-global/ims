-- ============================================================
-- INSERT DATA for beyourdi_cms-uat
-- Generated for local development
-- Login: admin@beyourdiary.com / Admin@1234
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- 1. projects (System settings - ID=1 required by system_setting.php)
-- ============================================================
INSERT INTO `projects` (`id`, `project_title`, `company_name`, `company_address`, `company_contact`, `company_email`, `company_business_no`, `meta`, `logo`, `meta_logo`, `themesColor`, `buttonColor`, `finance_year`, `barcode_prefix`, `barcode_next_number`, `barcode_date_time`, `barcode_encode`, `invoice_prefix_credit`, `invoice_next_number_credit`, `invoice_prefix_debit`, `invoice_next_number_debit`) VALUES
(1, 'BeYourDiary CMS', 'BeYourDiary Sdn Bhd', '123 Jalan Bukit Bintang, 55100 Kuala Lumpur, Malaysia', '+60123456789', 'admin@beyourdiary.com', 'BYD-202301', 'BeYourDiary Content Management System', 'logo2.png', 'logo2.png', '#4e73df', '#1cc88a', '2026-01-01', 'BYD', '1001', 'Y', 'Y', 'CR-INV', 1001, 'DB-INV', 1001);

-- ============================================================
-- 2. pin (Permission actions)
-- ============================================================
INSERT INTO `pin` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'View', 'View permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Add', 'Add permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Edit', 'Edit permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Delete', 'Delete permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Import', 'Import permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Export', 'Export permission', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 3. pin_group (Page/module groups with allowed actions)
-- Each pin_group.id maps to the pin numbers used in menu_bar.php
-- ============================================================
INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Pin', '1,2,3,4', 'Pin management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Pin Group', '1,2,3,4', 'Pin group management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'User Group', '1,2,3,4', 'User group management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Designations', '1,2,3,4', 'Designation management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Departments', '1,2,3,4', 'Department management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Holidays', '1,2,3,4', 'Holiday management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'Dashboard', '1', 'Dashboard view', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(8, 'Bank', '1,2,3,4', 'Bank management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(9, 'Brand', '1,2,3,4', 'Brand management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(10, 'Currency Unit', '1,2,3,4', 'Currency unit management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(11, 'Currencies', '1,2,3,4', 'Currency management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(12, 'Employment Type Status', '1,2,3,4', 'Employment type status management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(13, 'Marital Status', '1,2,3,4', 'Marital status management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(14, 'Platform', '1,2,3,4', 'Platform management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(15, 'Product Status', '1,2,3,4', 'Product status management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(16, 'Warehouse', '1,2,3,4', 'Warehouse management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(17, 'Rate Checking', '1', 'Rate checking', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(18, 'Audit Log', '1', 'Audit log view', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(19, 'Weight Unit', '1,2,3,4', 'Weight unit management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(20, 'Product', '1,2,3,4,5,6', 'Product management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(21, 'Package', '1,2,3,4', 'Package management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(22, 'Barcode Generator', '1,2,3,4', 'Barcode generator', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(23, 'Theme Setting', '1,2,3', 'Theme setting', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(24, 'Leave Type', '1,2,3,4', 'Leave type management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(25, 'Change Password', '1,3', 'Change password', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(26, 'Identity Type', '1,2,3,4', 'Identity type management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(27, 'Leave Status', '1,2,3,4', 'Leave status management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(28, 'Race', '1,2,3,4', 'Race management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(29, 'Customer Segmentation', '1,2,3,4', 'Customer segmentation management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(30, 'Socso Category', '1,2,3,4', 'Socso category management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(31, 'Employee EPF Rate', '1,2,3,4', 'Employee EPF rate management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(32, 'Employer EPF Rate', '1,2,3,4', 'Employer EPF rate management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(33, 'Payment Method', '1,2,3,4', 'Payment method management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(34, 'Employee Details', '1,2,3,4', 'Employee details management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(35, 'Tag', '1,2,3,4', 'Tag management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(36, 'Merchant', '1,2,3,4', 'Merchant management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(37, 'Current Bank Account Transaction', '1,2,3,4', 'Current bank account transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(38, 'Customer Info', '1,2,3,4', 'Customer info management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(39, 'System Setting', '1,3', 'System setting', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(40, 'Investment Transaction', '1,2,3,4', 'Investment transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(41, 'Inventories Transaction', '1,2,3,4', 'Inventories transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(42, 'Sundry Debtors Transaction', '1,2,3,4', 'Sundry debtors transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(43, 'Other Creditor Transaction', '1,2,3,4', 'Other creditor transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(44, 'Sundry Debtors Transaction', '1,2,3,4', 'Sundry debtors transaction view', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(45, 'Cash On Hand Transaction', '1,2,3,4', 'Cash on hand transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(46, 'Meta Ads Account', '1,2,3,4', 'Meta ads account', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(47, 'Expense Type', '1,2,3,4', 'Expense type management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(48, 'Facebook Ads Top Up Transaction', '1,2,3,4', 'FB ads topup transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(49, 'Merchant Commission Record', '1,2,3,4', 'Merchant commission record', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(50, 'Courier Account', '1,2,3,4', 'Courier account management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(51, 'Shopee Withdrawal Transactions', '1,2,3,4', 'Shopee withdrawal transactions', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(52, 'Monthly Bank Transaction Backup', '1,2,3,4', 'Monthly bank transaction backup', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(56, 'Category', '1,2,3,4', 'Product category management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(57, 'Tax Setting', '1,2,3,4', 'Tax setting management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(58, 'Shopee Account Management', '1,2,3,4', 'Shopee account management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(60, 'Payment Method (Finance)', '1,2,3,4', 'Finance payment method', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(62, 'Agent', '1,2,3,4', 'Agent management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(63, 'Payment Terms', '1,2,3,4', 'Payment terms management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(65, 'Internal Consume Ticket/Credit', '1,2,3,4', 'Internal consume ticket credit', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(66, 'Delivery Fees Claim Record', '1,2,3,4', 'Delivery fees claim record', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(67, 'My Leave Transcation', '1,2,3,4', 'My leave transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(68, 'Approval Leave Transcation', '1,2,3,4', 'Approval leave transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(69, 'Facebook Order Request', '1,2,3,4', 'Facebook order request', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(70, 'Credit Notes (Invoice)', '1,2,3,4', 'Credit notes invoice', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(72, 'All Leave Transcation', '1', 'All leave transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(74, 'Brand Series', '1,2,3,4', 'Brand series management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(75, 'Facebook Customer Record (Deals)', '1,2,3,4', 'FB customer record deals', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(76, 'Facebook Page Account', '1,2,3,4', 'Facebook page account', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(77, 'Shopee Ads Top Up Transaction', '1,2,3,4', 'Shopee ads topup transaction', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(78, 'Stock Credit Top Up Record', '1,2,3,4', 'Stock credit topup record', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(79, 'Chanel (Social Media)', '1,2,3,4', 'Chanel social media', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(80, 'Payment Method (Shopee)', '1,2,3,4', 'Shopee payment method', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(81, 'Lazada Account Management', '1,2,3,4', 'Lazada account management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(82, 'Shopee SG Setting', '1,2,3,4', 'Shopee SG setting', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(83, 'Shopee Service Charges Rate Setting', '1,2,3,4', 'Shopee service charges rate', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(84, 'Website Customer Record (Deals)', '1,2,3,4', 'Website customer record deals', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(85, 'Shopee Customer Record (Deals)', '1,2,3,4', 'Shopee customer record deals', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(86, 'Shopee Order Request', '1,2,3,4', 'Shopee order request', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(87, 'Atome Transaction Backup Record', '1,2,3,4', 'Atome transaction backup', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(88, 'J&T Transaction Backup Record', '1,2,3,4', 'J&T transaction backup', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(89, 'Stripe Transaction Backup Record', '1,2,3,4', 'Stripe transaction backup', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(90, 'User', '1,2,3,4', 'User management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(91, 'Lazada Customer Record (Deals)', '1,2,3,4', 'Lazada customer record deals', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(92, 'Website Order Request', '1,2,3,4', 'Website order request', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(93, 'Lazada Order Request', '1,2,3,4', 'Lazada order request', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(94, 'Debit Notes (Invoice)', '1,2,3,4', 'Debit notes invoice', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(95, 'Order Process List', '1,2,3,4', 'Order process list', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(96, 'Facebook Order Request', '1,2,3,4', 'FB order request report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(97, 'Shopee Order Request', '1,2,3,4', 'Shopee order request report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(98, 'Website Order Request', '1,2,3,4', 'Website order request report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(99, 'Lazada Order Request', '1,2,3,4', 'Lazada order request report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(100, 'Sales Person Report', '1', 'Sales person report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(101, 'Payment Method Report', '1', 'Payment method report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(102, 'Package Report', '1', 'Package report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(103, 'Brand Report', '1', 'Brand report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(104, 'Stock List', '1,2,3,4,5,6', 'Stock list', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(105, 'Stock Report', '1', 'Stock report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(106, 'Stock Costing Setting', '1,2,3', 'Stock costing setting', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(121, 'Goal Target', '1,2,3,4', 'Goal target management', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(123, 'Shopee Order Report', '1', 'Shopee order report', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 4. user_group (Admin group with ALL pins and ALL permissions)
-- Format: [pinGroupId:actionId1,actionId2,...]+[pinGroupId2:actionId1,...]+...
-- ============================================================
INSERT INTO `user_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Super Admin', '[0:1]+[1:1,2,3,4]+[2:1,2,3,4]+[3:1,2,3,4]+[4:1,2,3,4]+[5:1,2,3,4]+[6:1,2,3,4]+[7:1]+[8:1,2,3,4]+[9:1,2,3,4]+[10:1,2,3,4]+[11:1,2,3,4]+[12:1,2,3,4]+[13:1,2,3,4]+[14:1,2,3,4]+[15:1,2,3,4]+[16:1,2,3,4]+[17:1]+[18:1]+[19:1,2,3,4]+[20:1,2,3,4,5,6]+[21:1,2,3,4]+[22:1,2,3,4]+[23:1,2,3]+[24:1,2,3,4]+[25:1,3]+[26:1,2,3,4]+[27:1,2,3,4]+[28:1,2,3,4]+[29:1,2,3,4]+[30:1,2,3,4]+[31:1,2,3,4]+[32:1,2,3,4]+[33:1,2,3,4]+[34:1,2,3,4]+[35:1,2,3,4]+[36:1,2,3,4]+[37:1,2,3,4]+[38:1,2,3,4]+[39:1,3]+[40:1,2,3,4]+[41:1,2,3,4]+[42:1,2,3,4]+[43:1,2,3,4]+[44:1,2,3,4]+[45:1,2,3,4]+[46:1,2,3,4]+[47:1,2,3,4]+[48:1,2,3,4]+[49:1,2,3,4]+[50:1,2,3,4]+[51:1,2,3,4]+[52:1,2,3,4]+[56:1,2,3,4]+[57:1,2,3,4]+[58:1,2,3,4]+[60:1,2,3,4]+[62:1,2,3,4]+[63:1,2,3,4]+[65:1,2,3,4]+[66:1,2,3,4]+[67:1,2,3,4]+[68:1,2,3,4]+[69:1,2,3,4]+[70:1,2,3,4]+[72:1]+[74:1,2,3,4]+[75:1,2,3,4]+[76:1,2,3,4]+[77:1,2,3,4]+[78:1,2,3,4]+[79:1,2,3,4]+[80:1,2,3,4]+[81:1,2,3,4]+[82:1,2,3,4]+[83:1,2,3,4]+[84:1,2,3,4]+[85:1,2,3,4]+[86:1,2,3,4,14,15]+[87:1,2,3,4]+[88:1,2,3,4]+[89:1,2,3,4]+[90:1,2,3,4]+[91:1,2,3,4]+[92:1,2,3,4]+[93:1,2,3,4]+[94:1,2,3,4]+[95:1,2,3,4]+[96:1,2,3,4]+[97:1,2,3,4]+[98:1,2,3,4]+[99:1,2,3,4]+[100:1]+[101:1]+[102:1]+[103:1]+[104:1,2,3,4,5,6]+[105:1]+[106:1,2,3]+[121:1,2,3,4]+[123:1]', 'Super Admin with all privileges', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 5. bank
-- ============================================================
INSERT INTO `bank` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Maybank', 'Malayan Banking Berhad', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'CIMB Bank', 'CIMB Group Holdings', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Public Bank', 'Public Bank Berhad', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'RHB Bank', 'RHB Banking Group', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Hong Leong Bank', 'Hong Leong Bank Berhad', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'AmBank', 'AmBank Group', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'UOB Bank', 'United Overseas Bank', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(8, 'OCBC Bank', 'Oversea-Chinese Banking Corporation', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(9, 'DBS Bank', 'DBS Bank Ltd', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(10, 'HSBC Bank', 'HSBC Bank Malaysia', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 6. brand
-- ============================================================
INSERT INTO `brand` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'BeYourDiary', 'Main brand', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Urbanista', 'Secondary brand', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'DiaryCraft', 'Craft brand', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 7. brand_series
-- ============================================================
INSERT INTO `brand_series` (`id`, `name`, `brand`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Classic Series', '1', 'Classic diary line', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Premium Series', '1', 'Premium diary line', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Urban Series', '2', 'Urban lifestyle line', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Essential Series', '3', 'Essential products', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 8. countries (Essential countries for the system)
-- ============================================================
INSERT INTO `countries` (`id`, `code`, `name`, `nicename`, `iso3`, `numcode`, `phonecode`, `status`) VALUES
(1, 'MY', 'MALAYSIA', 'Malaysia', 'MYS', 458, 60, 'A'),
(2, 'SG', 'SINGAPORE', 'Singapore', 'SGP', 702, 65, 'A'),
(3, 'ID', 'INDONESIA', 'Indonesia', 'IDN', 360, 62, 'A'),
(4, 'TH', 'THAILAND', 'Thailand', 'THA', 764, 66, 'A'),
(5, 'PH', 'PHILIPPINES', 'Philippines', 'PHL', 608, 63, 'A'),
(6, 'VN', 'VIET NAM', 'Vietnam', 'VNM', 704, 84, 'A'),
(7, 'BN', 'BRUNEI DARUSSALAM', 'Brunei', 'BRN', 96, 673, 'A'),
(8, 'CN', 'CHINA', 'China', 'CHN', 156, 86, 'A'),
(9, 'JP', 'JAPAN', 'Japan', 'JPN', 392, 81, 'A'),
(10, 'KR', 'KOREA, REPUBLIC OF', 'South Korea', 'KOR', 410, 82, 'A'),
(11, 'US', 'UNITED STATES', 'United States', 'USA', 840, 1, 'A'),
(12, 'GB', 'UNITED KINGDOM', 'United Kingdom', 'GBR', 826, 44, 'A'),
(13, 'AU', 'AUSTRALIA', 'Australia', 'AUS', 36, 61, 'A'),
(14, 'IN', 'INDIA', 'India', 'IND', 356, 91, 'A'),
(15, 'HK', 'HONG KONG', 'Hong Kong', 'HKG', 344, 852, 'A'),
(16, 'TW', 'TAIWAN', 'Taiwan', 'TWN', 158, 886, 'A'),
(17, 'AE', 'UNITED ARAB EMIRATES', 'United Arab Emirates', 'ARE', 784, 971, 'A'),
(18, 'NZ', 'NEW ZEALAND', 'New Zealand', 'NZL', 554, 64, 'A'),
(19, 'SA', 'SAUDI ARABIA', 'Saudi Arabia', 'SAU', 682, 966, 'A'),
(20, 'MM', 'MYANMAR', 'Myanmar', 'MMR', 104, 95, 'A');

-- ============================================================
-- 9. courier
-- ============================================================
INSERT INTO `courier` (`id`, `courierID`, `name`, `country`, `taxable`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`, `tracking_link`) VALUES
('1', 'JT-MY', 'J&T Express (MY)', '1', 'Y', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A', 'https://www.jtexpress.my/tracking?'),
('2', 'POS-MY', 'Pos Laju', '1', 'Y', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A', 'https://www.pos.com.my/track-parcel'),
('3', 'DHL-MY', 'DHL Express', '1', 'Y', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A', 'https://www.dhl.com/my-en/home/tracking.html'),
('4', 'NJ-SG', 'Ninja Van (SG)', '2', 'Y', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A', 'https://www.ninjavan.co/en-sg/tracking'),
('5', 'SPX-MY', 'Shopee Express (MY)', '1', 'N', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A', NULL);

-- ============================================================
-- 10. currency_unit
-- ============================================================
INSERT INTO `currency_unit` (`id`, `unit`, `remark`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'MYR', 'Malaysian Ringgit', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'SGD', 'Singapore Dollar', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'USD', 'US Dollar', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(4, 'IDR', 'Indonesian Rupiah', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(5, 'THB', 'Thai Baht', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 11. currencies (Exchange rates)
-- ============================================================
INSERT INTO `currencies` (`id`, `default_currency_unit`, `exchange_currency_rate`, `exchange_currency_unit`, `remark`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 1, 1.0000, 1, 'MYR to MYR (base)', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 2, 3.4500, 1, 'SGD to MYR', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 3, 4.4700, 1, 'USD to MYR', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(4, 4, 0.0003, 1, 'IDR to MYR', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(5, 5, 0.1300, 1, 'THB to MYR', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 12. department
-- ============================================================
INSERT INTO `department` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Management', 'Management department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Sales', 'Sales department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Marketing', 'Marketing department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Operations', 'Operations department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Finance', 'Finance department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'IT', 'IT department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'Customer Service', 'Customer service department', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 13. designation
-- ============================================================
INSERT INTO `designation` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Director', 'Company director', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Manager', 'Department manager', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Senior Executive', 'Senior level executive', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Executive', 'Executive level', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Intern', 'Internship position', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 14. em_type_status (Employment type)
-- ============================================================
INSERT INTO `em_type_status` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Full-Time', 'Full time employment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Part-Time', 'Part time employment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Contract', 'Contract employment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Probation', 'Probation period', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Resigned', 'Resigned', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Terminated', 'Terminated', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 15. employee_epf_rate
-- ============================================================
INSERT INTO `employee_epf_rate` (`id`, `epf_rate`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 11.00, 'Standard EPF rate 11%', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 9.00, 'Reduced EPF rate 9%', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 7.00, 'Minimum EPF rate 7%', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 16. employer_epf_rate
-- ============================================================
INSERT INTO `employer_epf_rate` (`id`, `epf_rate`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 13.00, 'Employer EPF rate 13%', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 12.00, 'Employer EPF rate 12%', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 17. expense_type (in CMS DB)
-- ============================================================
INSERT INTO `expense_type` (`id`, `name`, `code`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Facebook Ads', 'FB-ADS', 'Facebook advertising expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Shopee Ads', 'SHP-ADS', 'Shopee advertising expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Delivery Fee', 'DEL-FEE', 'Delivery fee expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Packaging Cost', 'PKG-COST', 'Packaging material cost', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Office Supplies', 'OFC-SUP', 'Office supplies expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Salary', 'SAL', 'Salary expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'Rental', 'RENT', 'Office/warehouse rental', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(8, 'Utilities', 'UTIL', 'Utilities expense', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 18. holiday
-- ============================================================
INSERT INTO `holiday` (`id`, `name`, `date`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'New Year', '2026-01-01', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Thaipusam', '2026-01-25', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Federal Territory Day', '2026-02-01', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Labour Day', '2026-05-01', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Hari Raya Aidilfitri', '2026-03-20', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Malaysia Day', '2026-09-16', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'National Day', '2026-08-31', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(8, 'Deepavali', '2026-10-20', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(9, 'Christmas Day', '2026-12-25', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(10, 'Chinese New Year', '2026-02-17', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 19. identity_type
-- ============================================================
INSERT INTO `identity_type` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'NRIC', 'Malaysian IC', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Passport', 'International passport', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Military IC', 'Military identification', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 20. language
-- ============================================================
INSERT INTO `language` (`id`, `name`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'English', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'Bahasa Malaysia', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'Chinese', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 21. leave_type
-- ============================================================
INSERT INTO `leave_type` (`id`, `name`, `num_of_days`, `leave_status`, `auto_assign`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'Annual Leave', '14', '1', 'yes', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'Medical Leave', '14', '1', 'yes', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'Emergency Leave', '5', '1', 'yes', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(5, 'Maternity Leave', '60', '1', 'no', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(7, 'Unpaid Leave', '0', '1', 'no', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(8, 'Hospitalization Leave', '60', '1', 'yes', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 22. leave_status
-- ============================================================
INSERT INTO `leave_status` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Paid', 'Paid leave', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Unpaid', 'Unpaid leave', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 23. marital_status
-- ============================================================
INSERT INTO `marital_status` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Single', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Married', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Divorced', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Widowed', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 24. platform
-- ============================================================
INSERT INTO `platform` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Facebook', 'Facebook platform', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Shopee (MY)', 'Shopee Malaysia', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Shopee (SG)', 'Shopee Singapore', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Lazada', 'Lazada platform', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Website', 'Official website', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'TikTok Shop', 'TikTok Shop platform', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'Offline', 'Offline / walk-in', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 25. product_status
-- ============================================================
INSERT INTO `product_status` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'In Stock', 'Available in warehouse', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Sold', 'Product sold', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Damaged', 'Product damaged', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Returned', 'Product returned by customer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Reserved', 'Product reserved for order', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'Transferred', 'Product transferred', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 26. product_category
-- ============================================================
INSERT INTO `product_category` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Diary', 'Diary products', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Planner', 'Planner products', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Notebook', 'Notebook products', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Accessories', 'Diary accessories', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Gift Set', 'Gift set packages', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 27. product
-- ============================================================
INSERT INTO `product` (`id`, `name`, `brand`, `weight`, `weight_unit`, `cost`, `currency_unit`, `barcode_status`, `barcode_slot`, `product_category`, `expire_date`, `parent_product`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'Classic Diary A5', '1', '350', '1', '15.00', '1', NULL, '1', '1', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'Premium Diary B5', '1', '500', '1', '25.00', '1', NULL, '1', '1', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'Weekly Planner', '1', '300', '1', '12.00', '1', NULL, '1', '2', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(4, 'Urban Notebook', '2', '250', '1', '10.00', '1', NULL, '1', '3', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(5, 'Diary Pen Set', '1', '50', '1', '5.00', '1', NULL, '1', '4', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(6, 'Premium Gift Set', '1', '800', '1', '45.00', '1', NULL, '1', '5', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(7, 'Craft Notebook Mini', '3', '150', '1', '8.00', '1', NULL, '1', '3', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(8, 'Essential Planner A4', '3', '450', '1', '18.00', '1', NULL, '1', '2', NULL, NULL, '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 28. package
-- ============================================================
INSERT INTO `package` (`id`, `name`, `brand`, `cost`, `cost_curr`, `agent_cost`, `price`, `currency_unit`, `product`, `barcode_slot_total`, `remark`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'Classic Single Pack', 1, 15.00, 1, 0, 39.90, '1', '1', '1', 'Single diary pack', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'Premium Single Pack', 1, 25.00, 1, 0, 69.90, '1', '2', '1', 'Premium diary pack', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'Duo Bundle Pack', 1, 30.00, 1, 0, 79.90, '1', '1,3', '2', 'Diary + planner bundle', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(4, 'Gift Set Premium', 1, 45.00, 1, 0, 119.90, '1', '6', '1', 'Premium gift set package', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(5, 'Urban Starter Pack', 2, 10.00, 1, 0, 29.90, '1', '4', '1', 'Urban notebook starter', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(6, 'Essential Bundle', 3, 26.00, 1, 0, 59.90, '1', '7,8', '2', 'Essential diary bundle', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 29. payment_method
-- ============================================================
INSERT INTO `payment_method` (`id`, `name`, `installment_period`, `service_rate`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Online Banking (FPX)', 0, 0, 'FPX online payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Credit Card', 0, 2.5, 'Credit card payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Debit Card', 0, 0, 'Debit card payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Cash', 0, 0, 'Cash payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Bank Transfer', 0, 0, 'Bank transfer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(6, 'E-Wallet', 0, 0, 'E-wallet payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(7, 'Atome', 3, 0, 'Atome BNPL', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(8, 'Stripe', 0, 2.9, 'Stripe online payment', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 30. race
-- ============================================================
INSERT INTO `race` (`id`, `name`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Malay', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Chinese', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Indian', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'Others', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 31. socso_category
-- ============================================================
INSERT INTO `socso_category` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Category 1', 'Employment Injury & Invalidity', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Category 2', 'Employment Injury Only', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 32. warehouse
-- ============================================================
INSERT INTO `warehouse` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'HQ Warehouse', 'Main headquarters warehouse', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'SG Warehouse', 'Singapore warehouse', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Backup Warehouse', 'Backup/overflow warehouse', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 33. weight_unit
-- ============================================================
INSERT INTO `weight_unit` (`id`, `unit`, `remark`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `status`) VALUES
(1, 'g', 'Gram', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(2, 'kg', 'Kilogram', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A'),
(3, 'oz', 'Ounce', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 'A');

-- ============================================================
-- 34. tag
-- ============================================================
INSERT INTO `tag` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'VIP', 'VIP customer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Wholesale', 'Wholesale buyer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Repeat Customer', 'Returning customer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'New Customer', 'First time buyer', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 35. customer_segmentation
-- ============================================================
INSERT INTO `customer_segmentation` (`id`, `name`, `colorCode`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`, `boxFrom`, `boxUntil`, `brandSeries`, `remark`) VALUES
(1, 'Bronze', '#CD7F32', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A', 0.00, 499.99, 1, 'Entry level customer'),
(2, 'Silver', '#C0C0C0', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A', 500.00, 1499.99, 1, 'Regular customer'),
(3, 'Gold', '#FFD700', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A', 1500.00, 4999.99, 1, 'Premium customer'),
(4, 'Platinum', '#E5E4E2', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A', 5000.00, 99999.99, 1, 'Top tier customer');

-- ============================================================
-- 36. employee_personal_info (Admin user employee record)
-- ============================================================
INSERT INTO `employee_personal_info` (`id`, `name`, `email`, `id_type`, `id_number`, `gender`, `date_of_birth`, `residence_status`, `nationality`, `marital_status`, `no_of_children`, `race_id`, `address_line_1`, `address_line_2`, `city`, `state`, `postcode`, `phone_number`, `alternate_phone_number`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_relationship`, `preferred_payment_method`, `bank_id`, `account_holders_name`, `account_number`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Admin User', 'admin@beyourdiary.com', 1, '900101-10-1234', 'Male', '1990-01-01', 'Resident', 1, 1, 0, 2, '123 Jalan Admin', 'Taman Admin', 'Kuala Lumpur', 'WP Kuala Lumpur', '50000', '0123456789', '0198765432', 'Emergency Contact', '0111234567', 'Spouse', 1, 1, 'Admin User', '1234567890', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Sarah Tan', 'sarah@beyourdiary.com', 1, '920315-08-5678', 'Female', '1992-03-15', 'Resident', 1, 1, 0, 2, '456 Jalan Petaling', 'SS2', 'Petaling Jaya', 'Selangor', '47300', '0129876543', NULL, 'John Tan', '0127654321', 'Father', 1, 2, 'Sarah Tan', '2345678901', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Ahmad Faiz', 'faiz@beyourdiary.com', 1, '950720-03-9012', 'Male', '1995-07-20', 'Resident', 1, 2, 1, 1, '789 Jalan Ampang', NULL, 'Ampang', 'Selangor', '68000', '0171234567', NULL, 'Aminah Faiz', '0181234567', 'Mother', 1, 1, 'Ahmad Faiz', '3456789012', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 37. employee_info
-- ============================================================
INSERT INTO `employee_info` (`id`, `employee_id`, `join_date`, `position`, `employment_status_id`, `department_id`, `salary_frequency`, `salary`, `currency_unit_id`, `allowance`, `managers_for_leave_approval`, `remark`, `contributing_epf`, `contributing_epf_no`, `employee_epf_rate_id`, `employer_epf_rate_id`, `employee_tax_number`, `socso_category_id`, `eis`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, '2024-01-01', '1', 1, 1, 'monthly', 8000.00, 1, 500.00, NULL, 'System admin', 'Yes', 'EPF001', 1, 1, 'TAX001', 1, 'Yes', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 2, '2024-06-01', '3', 1, 2, 'monthly', 4500.00, 1, 200.00, '1', 'Sales senior executive', 'Yes', 'EPF002', 1, 1, 'TAX002', 1, 'Yes', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 3, '2025-01-15', '4', 1, 3, 'monthly', 3500.00, 1, 150.00, '1', 'Marketing executive', 'Yes', 'EPF003', 1, 1, 'TAX003', 1, 'Yes', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 38. employee_leave
-- ============================================================
INSERT INTO `employee_leave` (`id`, `employeeID`, `leaveType_1`, `leaveType_8`, `leaveType_2`, `leaveType_3`, `leaveType_5`, `leaveType_7`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 14, 60, 14, 5, 0, 0, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 2, 14, 60, 14, 5, 0, 0, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 3, 14, 60, 14, 5, 0, 0, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 39. user (Login accounts)
-- Password: Admin@1234 -> md5 = 4de93544234adffbb681ed60ffcfb941
-- ============================================================
INSERT INTO `user` (`id`, `name`, `username`, `password`, `password_alt`, `email`, `access_id`, `employee_id`, `status`, `create_date`, `create_time`, `create_by`, `update_date`, `update_time`, `update_by`, `fail_count`) VALUES
(1, 'Admin User', 'admin', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'admin@beyourdiary.com', 1, 1, 'A', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 0),
(2, 'Sarah Tan', 'sarah', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'sarah@beyourdiary.com', 1, 2, 'A', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 0),
(3, 'Ahmad Faiz', 'faiz', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'faiz@beyourdiary.com', 1, 3, 'A', '2026-01-01', '00:00:00', '1', NULL, NULL, NULL, 0);

-- ============================================================
-- 40. customer_info (Sample customers)
-- ============================================================
INSERT INTO `customer_info` (`id`, `name`, `last_name`, `email`, `phone_country`, `phone_number`, `gender`, `birthday`, `shipping_name`, `shipping_last_name`, `shipping_address_1`, `shipping_address_2`, `shipping_city`, `shipping_state_province`, `shipping_zip_code`, `shipping_contact_number`, `shipping_country_region`, `shipping_company`, `default_segmentation`, `tags`, `person_in_charges`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Lim Wei Ming', 'Lim', 'weiming@example.com', '+60', '0121234567', 'Male', '1988-05-12', 'Lim Wei Ming', 'Lim', '10 Jalan Bunga Raya', 'Taman Bunga', 'Kuala Lumpur', 'WP Kuala Lumpur', '50000', '0121234567', 'Malaysia', NULL, '1', '1', '1', '1', '2026-01-15', '10:00:00', NULL, NULL, NULL, 'A'),
(2, 'Nurul Aisyah', 'Binti Hassan', 'nurul@example.com', '+60', '0139876543', 'Female', '1995-11-20', 'Nurul Aisyah', 'Hassan', '22 Jalan Melati', NULL, 'Shah Alam', 'Selangor', '40000', '0139876543', 'Malaysia', NULL, '2', '3', '2', '1', '2026-01-20', '14:00:00', NULL, NULL, NULL, 'A'),
(3, 'David Ong', 'Ong', 'david.ong@example.com', '+65', '91234567', 'Male', '1990-08-08', 'David Ong', 'Ong', '15 Orchard Road', '#05-12', 'Singapore', 'Singapore', '238839', '91234567', 'Singapore', 'Ong Trading', '1', '2', '1', '1', '2026-02-01', '09:30:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 41. yearly_goals (2026 goals)
-- ============================================================
INSERT INTO `yearly_goals` (`id`, `year`, `month`, `shopee_my_goal`, `shopee_sg_goal`, `lazada_goal`, `facebook_goal`, `website_goal`, `status`) VALUES
(1, 2026, 'Jan', 10000.00, 5000.00, 3000.00, 8000.00, 5000.00, 'A'),
(2, 2026, 'Feb', 12000.00, 6000.00, 3500.00, 9000.00, 5500.00, 'A'),
(3, 2026, 'Mar', 15000.00, 7000.00, 4000.00, 10000.00, 6000.00, 'A'),
(4, 2026, 'Apr', 14000.00, 6500.00, 3800.00, 9500.00, 5800.00, 'A'),
(5, 2026, 'May', 13000.00, 6000.00, 3500.00, 9000.00, 5500.00, 'A'),
(6, 2026, 'Jun', 16000.00, 7500.00, 4500.00, 11000.00, 6500.00, 'A'),
(7, 2026, 'Jul', 15000.00, 7000.00, 4000.00, 10000.00, 6000.00, 'A'),
(8, 2026, 'Aug', 14000.00, 6500.00, 3800.00, 9500.00, 5800.00, 'A'),
(9, 2026, 'Sep', 17000.00, 8000.00, 5000.00, 12000.00, 7000.00, 'A'),
(10, 2026, 'Oct', 18000.00, 8500.00, 5500.00, 13000.00, 7500.00, 'A'),
(11, 2026, 'Nov', 20000.00, 10000.00, 6000.00, 15000.00, 8000.00, 'A'),
(12, 2026, 'Dec', 25000.00, 12000.00, 8000.00, 18000.00, 10000.00, 'A');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
START TRANSACTION;

INSERT IGNORE INTO `pin` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
(14, 'Verify', 'Verify permission', '1', '2026-03-05', '00:00:00', 'A'),
(15, 'Profit View', 'View profit permission', '1', '2026-03-05', '00:00:00', 'A');

UPDATE `user_group` SET `pins` = REPLACE(`pins`, '+[86:1,2,3,4]+', '+[86:1,2,3,4,14,15]+') WHERE `id` = 1;

-- In case it already has it, or to prevent errors if we run it twice
UPDATE `user_group` SET `pins` = REPLACE(`pins`, '+[86:1,2,3,4,14,15,14,15]+', '+[86:1,2,3,4,14,15]+') WHERE `id` = 1;

INSERT IGNORE INTO `user_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) 
VALUES (2, 'Admin Group', (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t), 'Admin with full shopee permissions', '1', '2026-03-05', '00:00:00', 'A');

UPDATE `user_group` SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) WHERE `id` = 2;

INSERT IGNORE INTO `user_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) 
VALUES (3, 'Basic Group', (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t), 'Basic user with no verify and no profit view', '1', '2026-03-05', '00:00:00', 'A');

UPDATE `user_group` SET `pins` = REPLACE(`pins`, '+[86:1,2,3,4,14,15]+', '+[86:1,2,3,4]+') WHERE `id` = 3;

INSERT INTO `user` (`id`, `name`, `username`, `password`, `password_alt`, `email`, `access_id`, `employee_id`, `status`, `create_date`, `create_time`, `create_by`, `fail_count`)
VALUES 
(4, 'Admin Test 1', 'admin1', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'admin1@beyourdiary.com', 2, 4, 'A', '2026-03-05', '00:00:00', '1', 0),
(5, 'Admin Test 2', 'admin2', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'admin2@beyourdiary.com', 2, 5, 'A', '2026-03-05', '00:00:00', '1', 0),
(6, 'Admin Test 3', 'admin3', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'admin3@beyourdiary.com', 2, 6, 'A', '2026-03-05', '00:00:00', '1', 0),
(7, 'Basic Test 1', 'basic1', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'basic1@beyourdiary.com', 3, 7, 'A', '2026-03-05', '00:00:00', '1', 0),
(8, 'Basic Test 2', 'basic2', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'basic2@beyourdiary.com', 3, 8, 'A', '2026-03-05', '00:00:00', '1', 0),
(9, 'Basic Test 3', 'basic3', '4de93544234adffbb681ed60ffcfb941', '4de93544234adffbb681ed60ffcfb941', 'basic3@beyourdiary.com', 3, 9, 'A', '2026-03-05', '00:00:00', '1', 0)
ON DUPLICATE KEY UPDATE 
`access_id` = VALUES(`access_id`),
`password` = VALUES(`password`),
`password_alt` = VALUES(`password_alt`);

COMMIT;
