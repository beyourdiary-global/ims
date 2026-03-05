-- ============================================================
-- INSERT DATA for beyourdi_financial-uat
-- Generated for local development
-- All foreign keys reference data in beyourdi_cms-uat
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- 1. expense_type (Financial DB copy)
-- ============================================================
INSERT INTO `expense_type` (`id`, `name`, `code`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Facebook Ads', 'FB-ADS', 'Facebook advertising expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(2, 'Shopee Ads', 'SHP-ADS', 'Shopee advertising expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(3, 'Delivery Fee', 'DEL-FEE', 'Delivery fee expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(4, 'Packaging Cost', 'PKG-COST', 'Packaging material cost', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(5, 'Office Supplies', 'OFC-SUP', 'Office supplies expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(6, 'Salary', 'SAL', 'Salary expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(7, 'Rental', 'RENT', 'Office/warehouse rental', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(8, 'Utilities', 'UTIL', 'Utilities expense', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A');

-- ============================================================
-- 2. finance_payment_method
-- ============================================================
INSERT INTO `finance_payment_method` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Bank Transfer', 'Bank transfer payment', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(2, 'Cash', 'Cash payment', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(3, 'Cheque', 'Cheque payment', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(4, 'Online Banking', 'Online banking payment', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A'),
(5, 'Credit Card', 'Credit card payment', '1', '2026-01-01', '00:00:00', '', '0000-00-00', '00:00:00', 'A');

-- ============================================================
-- 3. payment_terms
-- ============================================================
INSERT INTO `payment_terms` (`id`, `name`, `description`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Net 15', 'Payment due within 15 days', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Net 30', 'Payment due within 30 days', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Net 60', 'Payment due within 60 days', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'COD', 'Cash on delivery', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Immediate', 'Payment due immediately', NULL, '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 4. merchant
-- ============================================================
INSERT INTO `merchant` (`id`, `name`, `business_no`, `contact`, `email`, `address`, `person_in_charges`, `person_in_charges_contact`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'DiarySupply Co.', 'BUS-2024-001', '0312345678', 'supply@diarysupply.com', '100 Jalan Industri, Shah Alam, Selangor', '1', '0312345678', 'Main diary paper supplier', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'PackageMaster Sdn Bhd', 'BUS-2024-002', '0398765432', 'info@packagemaster.com', '55 Jalan Packaging, Petaling Jaya, Selangor', '1', '0398765432', 'Packaging supplier', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'PrintPro Industries', 'BUS-2024-003', '0356781234', 'sales@printpro.com', '88 Jalan Print, Puchong, Selangor', '1', '0356781234', 'Printing services', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 5. agent
-- ============================================================
INSERT INTO `agent` (`id`, `name`, `brand`, `pic`, `contact`, `email`, `country`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Agent Lisa', 1, 1, '0171112222', 'lisa.agent@example.com', 1, 'Malaysia main agent', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Agent James', 1, 2, '91234567', 'james.agent@example.com', 2, 'Singapore agent', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Agent Priya', 2, 1, '0181234567', 'priya.agent@example.com', 1, 'Urbanista agent', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 6. facebook_page_account
-- ============================================================
INSERT INTO `facebook_page_account` (`id`, `name`, `description`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'BeYourDiary Official', 'Main FB page for BeYourDiary', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'BeYourDiary Malaysia', 'Malaysia FB page', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Urbanista Official', 'Urbanista brand FB page', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 7. meta_ads_account
-- ============================================================
INSERT INTO `meta_ads_account` (`id`, `accID`, `accName`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'ACT_1234567890', 'BYD Main Ads Account', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'ACT_0987654321', 'BYD Secondary Ads Account', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 8. chanel_social_media
-- ============================================================
INSERT INTO `chanel_social_media` (`id`, `name`, `description`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'Facebook Messenger', 'Facebook Messenger channel', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'WhatsApp', 'WhatsApp channel', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Instagram DM', 'Instagram direct message', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'TikTok', 'TikTok channel', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(5, 'Telegram', 'Telegram channel', NULL, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 9. shopee_account
-- ============================================================
INSERT INTO `shopee_account` (`id`, `name`, `country`, `currency_unit`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'BeYourDiary MY', 1, '1', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'BeYourDiary SG', 2, '2', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Urbanista MY', 1, '1', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 10. lazada_account
-- ============================================================
INSERT INTO `lazada_account` (`id`, `name`, `country`, `currency_unit`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'BeYourDiary Lazada MY', '1', '1', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'BeYourDiary Lazada SG', '2', '2', '1', '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 11. shopee_payment_method
-- ============================================================
INSERT INTO `shopee_payment_method` (`id`, `name`, `fees`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'ShopeePay', 1.50, 'Shopee e-wallet', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 'Credit/Debit Card', 2.00, 'Card payment via Shopee', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 'Online Banking', 1.00, 'FPX through Shopee', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(4, 'COD', 0.00, 'Cash on delivery', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 12. shopee_service_charges_rate_setting
-- ============================================================
INSERT INTO `shopee_service_charges_rate_setting` (`id`, `currency_unit`, `commission`, `service`, `transaction`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 5.00, 2.00, 2.00, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 2, 4.00, 2.00, 2.12, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 13. shopee_sg_fees_setting
-- ============================================================
INSERT INTO `shopee_sg_fees_setting` (`id`, `commission`, `service`, `transaction`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 4.00, 2.00, 2.12, 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 14. tax_setting
-- ============================================================
INSERT INTO `tax_setting` (`id`, `country`, `name`, `percentage`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 'SST', 6.00, 'Sales and Service Tax (Malaysia)', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(2, 2, 'GST', 9.00, 'Goods and Services Tax (Singapore)', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A'),
(3, 3, 'PPN', 11.00, 'Pajak Pertambahan Nilai (Indonesia)', 1, '2026-01-01', '00:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 15. shopee_customer_info (Sample shopee customers)
-- ============================================================
INSERT INTO `shopee_customer_info` (`id`, `buyer_username`, `pic`, `country`, `brand`, `series`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'shopper_diarylovemy', 1, 1, 1, 1, 'Regular Shopee buyer', '1', '2026-01-15', '10:00:00', NULL, NULL, NULL, 'A'),
(2, 'sg_planner_fan', 2, 2, 1, 2, 'SG buyer interested in premium', '1', '2026-01-20', '14:00:00', NULL, NULL, NULL, 'A'),
(3, 'urban_notebook_user', 1, 1, 2, 3, 'Urbanista fan', '1', '2026-02-01', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 16. facebook_order_request (Sample FB orders)
-- ============================================================
INSERT INTO `facebook_order_request` (`id`, `name`, `fb_link`, `contact`, `sales_pic`, `country`, `brand`, `series`, `package`, `barcode_slot`, `fb_page`, `channel`, `price`, `pay_method`, `ship_rec_name`, `ship_rec_add`, `ship_rec_contact`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`, `order_status`) VALUES
(1, 'Lim Wei Ming', 'https://facebook.com/user1', '0121234567', 1, 1, 1, 1, 1, '1', 1, 1, 39.90, 1, 'Lim Wei Ming', '10 Jalan Bunga Raya, KL', '0121234567', 'First order', NULL, '1', '2026-01-15', '10:30:00', NULL, NULL, NULL, 'A', 'C'),
(2, 'Nurul Aisyah', 'https://facebook.com/user2', '0139876543', 2, 1, 1, 2, 2, '1', 2, 2, 69.90, 5, 'Nurul Aisyah', '22 Jalan Melati, Shah Alam', '0139876543', 'Premium diary order', NULL, '1', '2026-02-01', '14:00:00', NULL, NULL, NULL, 'A', 'C'),
(3, 'David Ong', 'https://facebook.com/user3', '91234567', 1, 2, 2, 3, 5, '1', 1, 1, 29.90, 1, 'David Ong', '15 Orchard Road, Singapore', '91234567', 'Urban brand order', NULL, '1', '2026-02-10', '11:00:00', NULL, NULL, NULL, 'A', 'P');

-- ============================================================
-- 17. shopee_sg_order_request (Sample Shopee SG orders - needed for dashboard)
-- ============================================================
INSERT INTO `shopee_sg_order_request` (`id`, `shopee_acc`, `currency`, `orderID`, `date`, `time`, `package`, `barcode_slot`, `brand`, `buyer`, `buyer_pay_meth`, `pic`, `price`, `voucher`, `act_shipping_fee`, `service_fee`, `trans_fee`, `ams_fee`, `fees`, `final_amt`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`, `order_status`) VALUES
(1, 2, 2, 'SG-ORD-20260115-001', '2026-01-15', '10:00:00', 1, '1', 1, 2, 1, 1, 15.90, 0.00, 0.00, 0.64, 0.34, 0.00, 0.98, 14.92, 'First SG order', '1', '2026-01-15', '10:00:00', NULL, NULL, NULL, 'A', 'C'),
(2, 2, 2, 'SG-ORD-20260120-001', '2026-01-20', '14:00:00', 2, '1', 1, 2, 2, 2, 29.90, 2.00, 0.00, 1.12, 0.59, 0.00, 1.71, 26.19, 'Premium order', '1', '2026-01-20', '14:00:00', NULL, NULL, NULL, 'A', 'C'),
(3, 2, 2, 'SG-ORD-20260201-001', '2026-02-01', '09:00:00', 4, '1', 1, 1, 1, 1, 45.90, 0.00, 3.99, 1.84, 0.97, 0.00, 2.81, 39.10, 'Gift set order', '1', '2026-02-01', '09:00:00', NULL, NULL, NULL, 'A', 'C'),
(4, 2, 2, 'SG-ORD-20260215-001', '2026-02-15', '16:30:00', 3, '2', 1, 2, 3, 2, 35.90, 5.00, 0.00, 1.24, 0.65, 0.00, 1.89, 29.01, 'Bundle discount order', '1', '2026-02-15', '16:30:00', NULL, NULL, NULL, 'A', 'C'),
(5, 2, 2, 'SG-ORD-20260301-001', '2026-03-01', '11:00:00', 1, '1', 1, 1, 1, 1, 15.90, 0.00, 0.00, 0.64, 0.34, 0.00, 0.98, 14.92, 'March order', '1', '2026-03-01', '11:00:00', NULL, NULL, NULL, 'A', 'P'),
(6, 2, 1, 'MY-ORD-20260115-001', '2026-01-15', '10:30:00', 1, '1', 1, 1, 1, 1, 39.90, 0.00, 0.00, 2.00, 0.80, 0.00, 2.80, 37.10, 'MY Shopee order via SG acc', '1', '2026-01-15', '10:30:00', NULL, NULL, NULL, 'A', 'C'),
(7, 2, 1, 'MY-ORD-20260201-001', '2026-02-01', '13:00:00', 2, '1', 1, 3, 2, 2, 69.90, 0.00, 5.50, 3.50, 1.40, 0.00, 4.90, 59.50, 'Premium diary MY', '1', '2026-02-01', '13:00:00', NULL, NULL, NULL, 'A', 'C'),
(8, 2, 1, 'MY-ORD-20260301-001', '2026-03-01', '15:00:00', 3, '2', 1, 1, 3, 1, 79.90, 10.00, 0.00, 3.50, 1.40, 0.00, 4.90, 65.00, 'Bundle pack March', '1', '2026-03-01', '15:00:00', NULL, NULL, NULL, 'A', 'P');

-- ============================================================
-- 18. website_order_request (Sample website orders)
-- ============================================================
INSERT INTO `website_order_request` (`id`, `order_id`, `brand`, `series`, `pkg`, `barcode_slot`, `country`, `currency`, `price`, `shipping`, `discount`, `total`, `pay_method`, `pic`, `cust_id`, `cust_name`, `cust_email`, `cust_birthday`, `shipping_name`, `shipping_address`, `shipping_contact`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`, `order_status`) VALUES
(1, 'WEB-20260115-001', '1', '1', '1', '1', '1', '1', 39.90, 8.00, 0.00, 47.90, '1', '1', '1', 'Lim Wei Ming', 'weiming@example.com', '1988-05-12', 'Lim Wei Ming', '10 Jalan Bunga Raya, KL 50000', '0121234567', 'Website order', 1, '2026-01-15', '12:00:00', NULL, NULL, NULL, 'A', 'C'),
(2, 'WEB-20260201-001', '1', '2', '4', '1', '2', '2', 45.90, 5.00, 0.00, 50.90, '8', '2', '3', 'David Ong', 'david.ong@example.com', '1990-08-08', 'David Ong', '15 Orchard Road, Singapore 238839', '91234567', 'Gift set SG', 1, '2026-02-01', '09:30:00', NULL, NULL, NULL, 'A', 'C'),
(3, 'WEB-20260301-001', '2', '3', '5', '1', '1', '1', 29.90, 8.00, 5.00, 32.90, '6', '1', '2', 'Nurul Aisyah', 'nurul@example.com', '1995-11-20', 'Nurul Aisyah', '22 Jalan Melati, Shah Alam 40000', '0139876543', 'Urbanista website order', 1, '2026-03-01', '10:00:00', NULL, NULL, NULL, 'A', 'P');

-- ============================================================
-- 19. asset_current_bank_acc_transaction (Sample bank transactions)
-- ============================================================
INSERT INTO `asset_current_bank_acc_transaction` (`id`, `transactionID`, `type`, `date`, `bank`, `currency`, `amount`, `prev_amt`, `final_amt`, `attachment`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'BANK-TXN-001', 'Add', '2026-01-05', 1, 1, 50000.00, 0.00, 50000.00, NULL, 'Initial capital deposit', '1', '2026-01-05', '09:00:00', NULL, NULL, NULL, 'A'),
(2, 'BANK-TXN-002', 'Add', '2026-01-15', 1, 1, 5000.00, 50000.00, 55000.00, NULL, 'Sales revenue deposit', '1', '2026-01-15', '14:00:00', NULL, NULL, NULL, 'A'),
(3, 'BANK-TXN-003', 'Deduct', '2026-02-01', 1, 1, 8000.00, 55000.00, 47000.00, NULL, 'Salary payment', '1', '2026-02-01', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 20. asset_initial_capital_transaction
-- ============================================================
INSERT INTO `asset_initial_capital_transaction` (`id`, `transactionID`, `date`, `currency`, `amount`, `description`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'CAP-TXN-001', '2026-01-01', 1, 100000.00, 'Initial business capital investment', 'Founder investment', NULL, '1', '2026-01-01', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 21. asset_cash_on_hand_transaction
-- ============================================================
INSERT INTO `asset_cash_on_hand_transaction` (`id`, `transactionID`, `type`, `pic`, `date`, `bank`, `currency`, `amount`, `prev_amt`, `final_amt`, `description`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'CASH-TXN-001', 'Add', 1, '2026-01-01', NULL, 1, 5000.00, 0.00, 5000.00, 'Petty cash fund', 'Initial petty cash setup', NULL, '1', '2026-01-01', '09:00:00', NULL, NULL, NULL, 'A'),
(2, 'CASH-TXN-002', 'Deduct', 1, '2026-01-10', NULL, 1, 200.00, 5000.00, 4800.00, 'Office supplies purchase', 'Stationery for office', NULL, '1', '2026-01-10', '11:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 22. asset_investment_transaction
-- ============================================================
INSERT INTO `asset_investment_transaction` (`id`, `transactionID`, `type`, `date`, `amount`, `prev_amt`, `final_amt`, `merchant`, `remarks`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'INV-TXN-001', 'Add', '2026-01-15', 20000.00, 0.00, 20000.00, 1, 'Investment in diary supplier', NULL, '1', '2026-01-15', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 23. asset_inventories_transaction
-- ============================================================
INSERT INTO `asset_inventories_transaction` (`id`, `transactionID`, `date`, `merchantID`, `itemID`, `unit_price`, `bal_qty`, `amount`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'INVT-TXN-001', '2026-01-10', 1, 1, 15.00, 200, 3000.00, 'Classic Diary A5 stock purchase', NULL, '1', '2026-01-10', '09:00:00', NULL, NULL, NULL, 'A'),
(2, 'INVT-TXN-002', '2026-01-10', 1, 2, 25.00, 100, 2500.00, 'Premium Diary B5 stock purchase', NULL, '1', '2026-01-10', '10:00:00', NULL, NULL, NULL, 'A'),
(3, 'INVT-TXN-003', '2026-02-01', 2, 3, 12.00, 150, 1800.00, 'Weekly Planner stock purchase', NULL, '1', '2026-02-01', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 24. asset_sundry_debtors_transactions
-- ============================================================
INSERT INTO `asset_sundry_debtors_transactions` (`id`, `transactionID`, `type`, `payment_date`, `debtors`, `amount`, `prev_amt`, `final_amt`, `description`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'SD-TXN-001', 'Add', '2026-01-20', 1, 2000.00, 0.00, 2000.00, 'Agent Lisa outstanding', 'Pending collection', NULL, '1', '2026-01-20', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 25. asset_other_creditor_transaction
-- ============================================================
INSERT INTO `asset_other_creditor_transaction` (`id`, `transactionID`, `type`, `date`, `creditor`, `amount`, `prev_amt`, `final_amt`, `description`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'OCR-TXN-001', 'Add', '2026-01-25', 1, 5000.00, 0.00, 5000.00, 'Supplier credit outstanding', 'Payment due Feb 2026', NULL, '1', '2026-01-25', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 26. merchant_commission
-- ============================================================
INSERT INTO `merchant_commission` (`id`, `merchantID`, `date`, `currency`, `amount`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '1', '2026-01-31', 1, 500.00, 'January commission', '1', '2026-01-31', '10:00:00', '', '0000-00-00', '00:00:00', 'A'),
(2, '2', '2026-02-28', 1, 350.00, 'February commission', '1', '2026-02-28', '10:00:00', '', '0000-00-00', '00:00:00', 'A');

-- ============================================================
-- 27. delivery_fees_claim_transaction
-- ============================================================
INSERT INTO `delivery_fees_claim_transaction` (`id`, `courier`, `currency`, `subtotal`, `tax`, `total`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '1', 1, 150.00, 9.00, 159.00, 'J&T Jan delivery claim', '1', '2026-01-31', '10:00:00', NULL, NULL, NULL, 'A'),
(2, '2', 1, 80.00, 4.80, 84.80, 'Pos Laju Feb delivery claim', '1', '2026-02-28', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 28. downline_top_up_record
-- ============================================================
INSERT INTO `downline_top_up_record` (`id`, `agent`, `brand`, `currency_unit`, `amount`, `attachment`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 1, 1, 1000.00, NULL, 'Agent Lisa January topup', 1, '2026-01-10', '09:00:00', NULL, NULL, NULL, 'A'),
(2, 2, 1, 2, 500.00, NULL, 'Agent James SG topup', 1, '2026-02-01', '14:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 29. internal_consume_item
-- ============================================================
INSERT INTO `internal_consume_item` (`id`, `date`, `pic`, `brand`, `package`, `cost`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '2026-01-15', 1, 1, 1, 15.00, 'Sample for photo shoot', 1, '2026-01-15', '10:00:00', NULL, NULL, NULL, 'A'),
(2, '2026-02-01', 2, 1, 4, 45.00, 'Gift set for influencer', 1, '2026-02-01', '14:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 30. internal_consume_ticket_credit_transaction
-- ============================================================
INSERT INTO `internal_consume_ticket_credit_transaction` (`id`, `date`, `PIC`, `brand`, `currency_unit`, `amount`, `remark`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '2026-01-20', 1, 1, 1, 100.00, 'Social media giveaway credit', NULL, '1', '2026-01-20', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 31. stock_credit_topup_record
-- ============================================================
INSERT INTO `stock_credit_topup_record` (`id`, `merchant`, `brand`, `currency_unit`, `amount`, `attachment`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '1', '1', '1', 3000.00, NULL, 'Jan stock credit topup', 1, '2026-01-10', '09:00:00', NULL, NULL, NULL, 'A'),
(2, '2', '1', '1', 2000.00, NULL, 'Feb stock credit topup', 1, '2026-02-01', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 32. shopee_withdrawal_transactions
-- ============================================================
INSERT INTO `shopee_withdrawal_transactions` (`id`, `date`, `swt_id`, `currency_unit`, `amount`, `pic`, `attachment`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '2026-01-31', 'SWT-20260131-001', '1', 2500.00, 1, NULL, 'Jan MY withdrawal', 1, '2026-01-31', '10:00:00', NULL, NULL, NULL, 'A'),
(2, '2026-01-31', 'SWT-20260131-002', '2', 800.00, 1, NULL, 'Jan SG withdrawal', 1, '2026-01-31', '11:00:00', NULL, NULL, NULL, 'A'),
(3, '2026-02-28', 'SWT-20260228-001', '1', 3500.00, 2, NULL, 'Feb MY withdrawal', 1, '2026-02-28', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 33. facebook_ads_topup_transaction
-- ============================================================
INSERT INTO `facebook_ads_topup_transaction` (`id`, `meta_acc`, `transactionID`, `payment_date`, `pic`, `topup_amt`, `attachment`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 'FBADS-TXN-001', '2026-01-10', 1, 500.00, '', 'January FB ads topup', '1', '2026-01-10', '09:00:00', '', '0000-00-00', '00:00:00', 'A'),
(2, 1, 'FBADS-TXN-002', '2026-02-01', 1, 800.00, '', 'February FB ads topup', '1', '2026-02-01', '09:00:00', '', '0000-00-00', '00:00:00', 'A'),
(3, 2, 'FBADS-TXN-003', '2026-02-15', 2, 300.00, '', 'Secondary acc topup', '1', '2026-02-15', '14:00:00', '', '0000-00-00', '00:00:00', 'A');

-- ============================================================
-- 34. shopee_ads_topup_transaction
-- ============================================================
INSERT INTO `shopee_ads_topup_transaction` (`id`, `shopee_acc`, `orderID`, `payment_date`, `currency`, `topup_amt`, `subtotal`, `gst`, `pay_meth`, `remark`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 'SHPADS-001', '2026-01-15 09:00:00', 1, 200.00, 188.68, 11.32, 1, 'MY Shopee ads Jan', '1', '2026-01-15', '09:00:00', NULL, NULL, NULL, 'A'),
(2, 2, 'SHPADS-002', '2026-02-01 10:00:00', 2, 150.00, 137.61, 12.39, 1, 'SG Shopee ads Feb', '1', '2026-02-01', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 35. credit_notes_invoice (Sample credit note)
-- ============================================================
INSERT INTO `credit_notes_invoice` (`id`, `projectID`, `invoice`, `date`, `due_date`, `currency`, `bill_nameID`, `bill_add`, `bill_email`, `bill_contact`, `products`, `pay_method`, `pay_details`, `pay_terms`, `sales_pic`, `remark`, `subtotal`, `discount`, `tax`, `total`, `inv_note`, `payment_status`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 'CR-INV-1001', '2026-01-20', '2026-02-20', 1, 1, '100 Jalan Industri, Shah Alam', 'supply@diarysupply.com', '0312345678', '1', 1, 'Bank Transfer - Maybank', 2, 1, 'Credit note for returned goods', 500.00, 0.00, 30.00, 530.00, 'Returned defective diaries', 'Paid', '1', '2026-01-20', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 36. cred_inv_products
-- ============================================================
INSERT INTO `cred_inv_products` (`id`, `invoice_row`, `description`, `price`, `quantity`, `amount`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '1', 'Classic Diary A5 - Defective', 25.00, 20, 500.00, '1', '2026-01-20', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 37. debit_notes_invoice (Sample debit note)
-- ============================================================
INSERT INTO `debit_notes_invoice` (`id`, `projectID`, `invoice`, `date`, `due_date`, `currency`, `bill_nameID`, `bill_add`, `bill_email`, `bill_contact`, `products`, `pay_method`, `pay_details`, `pay_terms`, `sales_pic`, `remark`, `subtotal`, `discount`, `tax`, `total`, `inv_note`, `payment_status`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, 'DB-INV-1001', '2026-02-01', '2026-03-01', 1, 2, '55 Jalan Packaging, PJ', 'info@packagemaster.com', '0398765432', '1', 1, 'Bank Transfer - Maybank', 2, 1, 'Packaging material purchase', 1200.00, 0.00, 72.00, 1272.00, 'Monthly packaging supply', 'Pending', '1', '2026-02-01', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 38. debit_inv_products
-- ============================================================
INSERT INTO `debit_inv_products` (`id`, `invoice_row`, `description`, `price`, `quantity`, `amount`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, '1', 'Custom Box - A5 size', 4.00, 200, 800.00, '1', '2026-02-01', '10:00:00', NULL, NULL, NULL, 'A'),
(2, '1', 'Custom Box - B5 size', 4.00, 100, 400.00, '1', '2026-02-01', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 39. bank_transaction_backup
-- ============================================================
INSERT INTO `bank_transaction_backup` (`id`, `year`, `month`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 2026, 1, NULL, '1', '2026-02-01', '09:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 40. jt_transaction_backup
-- ============================================================
INSERT INTO `jt_transaction_backup` (`id`, `number`, `date`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'JT-BK-20260131', '2026-01-31', NULL, 1, '2026-01-31', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 41. stripe_transaction_backup
-- ============================================================
INSERT INTO `stripe_transaction_backup` (`id`, `payout_id`, `date_paid`, `curr_unit`, `amount`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 1, '2026-01-31', 'MYR', 1500.00, NULL, 1, '2026-01-31', '10:00:00', NULL, NULL, NULL, 'A'),
(2, 2, '2026-02-28', 'MYR', 2200.00, NULL, 1, '2026-02-28', '10:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 42. atome_transaction_backup
-- ============================================================
INSERT INTO `atome_transaction_backup` (`id`, `trans_id`, `atome_id`, `date`, `trans_outlet`, `platform_id`, `amt_rec`, `attachment`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`) VALUES
(1, 'ATOME-TXN-001', 'ATOME-001', '2026-01-20', 'BeYourDiary Online', '1', 39.90, NULL, 1, '2026-01-20', '10:00:00', NULL, NULL, NULL, 'A'),
(2, 'ATOME-TXN-002', 'ATOME-002', '2026-02-10', 'BeYourDiary Online', '1', 69.90, NULL, 1, '2026-02-10', '14:00:00', NULL, NULL, NULL, 'A');

-- ============================================================
-- 43. lazada_order_request (in CMS DB, but ref here for completeness - skip if CMS already has)
-- Note: This table is actually in CMS DB. Keeping for reference only.
-- ============================================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
