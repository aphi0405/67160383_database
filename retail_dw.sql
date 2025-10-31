
-- retail_dw.sql
-- สร้างฐานข้อมูลและข้อมูลจำลองสำหรับ Dashboard ยอดขาย (MySQL 8+)
-- ผู้เขียน: ChatGPT
-- ใช้กับ MySQL 8 ขึ้นไป (มี CTE) แนะนำให้รันไฟล์นี้ทั้งก้อน

DROP DATABASE IF EXISTS retail_dw;
CREATE DATABASE retail_dw CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE retail_dw;

-- =========================
-- ตารางมิติ (Dimensions)
-- =========================

CREATE TABLE dim_date (
  date_key DATE PRIMARY KEY,
  y INT NOT NULL,
  m INT NOT NULL,
  d INT NOT NULL,
  month_name VARCHAR(12) NOT NULL,
  weekday INT NOT NULL,       -- 1=Mon ... 7=Sun (ตามภาษา SQL)
  weekday_name VARCHAR(12) NOT NULL,
  week_of_year INT NOT NULL
);

CREATE TABLE dim_product (
  product_id INT PRIMARY KEY AUTO_INCREMENT,
  product_name VARCHAR(100) NOT NULL,
  category VARCHAR(50) NOT NULL,
  brand VARCHAR(50) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL
);

CREATE TABLE dim_store (
  store_id INT PRIMARY KEY AUTO_INCREMENT,
  store_name VARCHAR(100) NOT NULL,
  region VARCHAR(50) NOT NULL,
  city VARCHAR(60) NOT NULL
);

CREATE TABLE dim_customer (
  customer_id INT PRIMARY KEY AUTO_INCREMENT,
  customer_name VARCHAR(100) NOT NULL,
  gender ENUM('M','F') NOT NULL,
  sign_up_date DATE NOT NULL
);

-- =========================
-- ตารางแฟกต์ (Fact)
-- =========================

CREATE TABLE fact_sales (
  sales_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  date_key DATE NOT NULL,
  product_id INT NOT NULL,
  store_id INT NOT NULL,
  customer_id INT NOT NULL,
  quantity INT NOT NULL,
  gross_amount DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('Cash','Credit Card','Mobile Pay','QR PromptPay') NOT NULL,
  hour_of_day INT NOT NULL,  -- 0..23
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (date_key) REFERENCES dim_date(date_key),
  FOREIGN KEY (product_id) REFERENCES dim_product(product_id),
  FOREIGN KEY (store_id) REFERENCES dim_store(store_id),
  FOREIGN KEY (customer_id) REFERENCES dim_customer(customer_id)
);

-- =========================
-- เติมมิติวันที่ 730 วัน (2 ปีย้อนหลัง)
-- =========================

WITH RECURSIVE d AS (
  SELECT DATE_SUB(CURDATE(), INTERVAL 729 DAY) AS dt
  UNION ALL
  SELECT DATE_ADD(dt, INTERVAL 1 DAY) FROM d WHERE dt < CURDATE()
)
INSERT INTO dim_date (date_key, y, m, d, month_name, weekday, weekday_name, week_of_year)
SELECT
  dt,
  YEAR(dt),
  MONTH(dt),
  DAY(dt),
  DATE_FORMAT(dt, '%b'),
  WEEKDAY(dt)+1,
  DATE_FORMAT(dt, '%a'),
  WEEK(dt, 3)
FROM d;

-- =========================
-- เติมมิติสินค้า
-- =========================

INSERT INTO dim_product (product_name, category, brand, unit_price) VALUES
 ('Iced Coffee 16oz','Beverage','CafeJoy', 45.00),
 ('Hot Americano','Beverage','CafeJoy', 40.00),
 ('Thai Milk Tea','Beverage','ChaThai', 35.00),
 ('Matcha Latte','Beverage','UjiLeaf', 55.00),
 ('Lemon Soda','Beverage','FizzUp', 30.00),
 ('Croissant','Bakery','Butter&Co', 42.00),
 ('Chocolate Donut','Bakery','SweetBite', 28.00),
 ('Ham Cheese Sandwich','Bakery','SnackBox', 55.00),
 ('Tuna Sandwich','Bakery','SnackBox', 58.00),
 ('Cheesecake','Dessert','CreamyHill', 85.00),
 ('Brownie','Dessert','CacaoFarm', 50.00),
 ('Pudding','Dessert','CreamyHill', 35.00),
 ('Granola Cup','Snack','FitLife', 49.00),
 ('Potato Chips','Snack','CrispyDay', 25.00),
 ('Mixed Nuts','Snack','Nutty', 60.00);

-- =========================
-- เติมมิติร้านค้า
-- =========================

INSERT INTO dim_store (store_name, region, city) VALUES
 ('Bangsaen Beach Branch','East','Chonburi'),
 ('BUU Campus Branch','East','Chonburi'),
 ('CentralWorld Kiosk','Central','Bangkok'),
 ('Silom Office Tower','Central','Bangkok'),
 ('Chiang Mai Nimman','North','Chiang Mai'),
 ('Hatyai Central','South','Songkhla');

-- =========================
-- เติมมิติลูกค้า (2,000 คน แบบสุ่ม)
-- =========================

-- สร้างตารางชั่วคราวของตัวอักษรเพื่อสุ่มชื่อ
CREATE TEMPORARY TABLE tmp_chars (c CHAR(1));
INSERT INTO tmp_chars VALUES ('A'),('B'),('C'),('D'),('E'),('F'),('G'),('H'),('I'),('J'),
('K'),('L'),('M'),('N'),('O'),('P'),('Q'),('R'),('S'),('T'),('U'),('V'),('W'),('X'),('Y'),('Z');

-- ใช้ตัวเลข 1..2000
WITH RECURSIVE nums AS (
  SELECT 1 AS n
  UNION ALL
  SELECT n+1 FROM nums WHERE n < 2000
)
INSERT INTO dim_customer (customer_name, gender, sign_up_date)
SELECT
  CONCAT('Cust-', n, '-', (SELECT c FROM tmp_chars ORDER BY RAND() LIMIT 1)) AS customer_name,
  IF(RAND() < 0.48, 'F', 'M') AS gender,
  DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND()*720) DAY) AS sign_up_date
FROM nums;

DROP TEMPORARY TABLE IF EXISTS tmp_chars;

-- =========================
-- เติมแฟกต์ยอดขาย (ประมาณ 60,000 แถว)
-- =========================
-- แนวคิด: สำหรับแต่ละวันและแต่ละสาขา สุ่มจำนวนบิล 10..80
-- แล้วสุ่มสินค้า/ลูกค้า/ชั่วโมง/วิธีจ่าย/ส่วนลด ฯลฯ

-- ตารางช่วยวันที่ x ร้านค้า
CREATE TEMPORARY TABLE tmp_date_store AS
SELECT d.date_key, s.store_id
FROM dim_date d
CROSS JOIN dim_store s;

-- สร้างลำดับบิลต่อวันต่อสาขา (จำนวนบิลสุ่มต่อวันต่อสาขา)
CREATE TEMPORARY TABLE tmp_receipts AS
SELECT
  t.date_key,
  t.store_id,
  -- จำนวนบิลต่อวันต่อสาขา: 10..80 โดยเพิ่มในวันเสาร์อาทิตย์
  LEAST(80, GREATEST(10,
    FLOOR(30 + 30*RAND() + IF(WEEKDAY(t.date_key) IN (5,6), 15, 0))
  )) AS bills
FROM tmp_date_store t;

-- ขยายเป็นบิลรายบรรทัด (1..bills)
WITH RECURSIVE seq AS (
  SELECT 1 AS n
  UNION ALL
  SELECT n+1 FROM seq WHERE n < 100
)
CREATE TEMPORARY TABLE tmp_bills AS
SELECT r.date_key, r.store_id, s.n AS bill_no
FROM tmp_receipts r
JOIN seq s ON s.n <= r.bills;

-- สร้างแถวขายต่อบิล (สุ่ม 1..4 รายการต่อบิล)
WITH RECURSIVE seq4 AS (
  SELECT 1 AS n
  UNION ALL
  SELECT n+1 FROM seq4 WHERE n < 4
)
INSERT INTO fact_sales
(date_key, product_id, store_id, customer_id, quantity, gross_amount, discount_amount, net_amount, payment_method, hour_of_day)
SELECT
  b.date_key,
  p.product_id,
  b.store_id,
  c.customer_id,
  qty.quantity,
  ROUND(p.unit_price * qty.quantity, 2) AS gross_amount,
  disc.discount_amount,
  ROUND(p.unit_price * qty.quantity - disc.discount_amount, 2) AS net_amount,
  pm.method,
  hr.h
FROM tmp_bills b
JOIN seq4 q ON q.n <= 1 + FLOOR(RAND()*3)   -- ต่อบิลสุ่ม 1..4 รายการ
JOIN dim_product p ON p.product_id >= 1 AND p.product_id <= (SELECT MAX(product_id) FROM dim_product)
JOIN dim_customer c ON c.customer_id >= 1 AND c.customer_id <= (SELECT MAX(customer_id) FROM dim_customer)
JOIN (
  SELECT 1 AS quantity UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
) qty ON RAND() < 0.8 OR qty.quantity IN (1,2) -- โอกาสสูงที่ 1-2 ชิ้น
JOIN (
  SELECT 0 AS h UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
  SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL
  SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23
) hr ON TRUE
JOIN (
  SELECT 'Cash' AS method UNION ALL SELECT 'Credit Card' UNION ALL SELECT 'Mobile Pay' UNION ALL SELECT 'QR PromptPay'
) pm ON TRUE
JOIN (
  SELECT 0 AS discount_amount UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 5 UNION ALL SELECT 8 UNION ALL SELECT 10
) disc ON RAND() < 0.3  -- 30% โอกาสมีส่วนลด
WHERE RAND() < CASE
  WHEN hr.h BETWEEN 7 AND 10 THEN 0.25   -- เช้า
  WHEN hr.h BETWEEN 11 AND 13 THEN 0.45  -- เที่ยง
  WHEN hr.h BETWEEN 14 AND 17 THEN 0.30  -- บ่าย
  WHEN hr.h BETWEEN 18 AND 21 THEN 0.25  -- เย็น
  ELSE 0.05 END
AND p.category IN ('Beverage','Bakery','Dessert','Snack');

-- ทำความสะอาดตารางชั่วคราว
DROP TEMPORARY TABLE IF EXISTS tmp_bills;
DROP TEMPORARY TABLE IF EXISTS tmp_receipts;
DROP TEMPORARY TABLE IF EXISTS tmp_date_store;

-- ดัชนีเพื่อความเร็ว
CREATE INDEX idx_fs_date ON fact_sales(date_key);
CREATE INDEX idx_fs_store ON fact_sales(store_id);
CREATE INDEX idx_fs_product ON fact_sales(product_id);
CREATE INDEX idx_fs_customer ON fact_sales(customer_id);
CREATE INDEX idx_fs_hour ON fact_sales(hour_of_day);

-- =========================
-- วิว/ควิกเมตริกสำหรับ Dashboard
-- =========================

CREATE OR REPLACE VIEW v_daily_sales AS
SELECT date_key, SUM(net_amount) AS net_sales, SUM(quantity) AS qty
FROM fact_sales
GROUP BY date_key;

CREATE OR REPLACE VIEW v_monthly_sales AS
SELECT CONCAT(y,'-',LPAD(m,2,'0')) AS ym, SUM(net_amount) AS net_sales
FROM dim_date d
JOIN fact_sales f ON f.date_key = d.date_key
GROUP BY y,m
ORDER BY y,m;

CREATE OR REPLACE VIEW v_sales_by_category AS
SELECT p.category, SUM(f.net_amount) AS net_sales
FROM fact_sales f
JOIN dim_product p ON p.product_id = f.product_id
GROUP BY p.category;

CREATE OR REPLACE VIEW v_sales_by_region AS
SELECT s.region, SUM(f.net_amount) AS net_sales
FROM fact_sales f
JOIN dim_store s ON s.store_id = f.store_id
GROUP BY s.region;

CREATE OR REPLACE VIEW v_top_products AS
SELECT p.product_name, SUM(f.quantity) AS qty_sold, SUM(f.net_amount) AS net_sales
FROM fact_sales f
JOIN dim_product p ON p.product_id = f.product_id
GROUP BY p.product_name
ORDER BY net_sales DESC
LIMIT 10;

CREATE OR REPLACE VIEW v_payment_share AS
SELECT payment_method, SUM(net_amount) AS net_sales
FROM fact_sales
GROUP BY payment_method;

CREATE OR REPLACE VIEW v_hourly_sales AS
SELECT hour_of_day, SUM(net_amount) AS net_sales
FROM fact_sales
GROUP BY hour_of_day
ORDER BY hour_of_day;

CREATE OR REPLACE VIEW v_new_vs_returning AS
SELECT
  d.date_key,
  SUM(CASE WHEN c.sign_up_date = d.date_key THEN f.net_amount ELSE 0 END) AS new_customer_sales,
  SUM(CASE WHEN c.sign_up_date < d.date_key THEN f.net_amount ELSE 0 END) AS returning_sales
FROM fact_sales f
JOIN dim_customer c ON c.customer_id = f.customer_id
JOIN dim_date d ON d.date_key = f.date_key
GROUP BY d.date_key;

-- ตัวอย่างสิทธิ์ (ปรับตามจริง)
-- GRANT SELECT ON retail_dw.* TO 'app'@'%' IDENTIFIED BY 'app_password';
