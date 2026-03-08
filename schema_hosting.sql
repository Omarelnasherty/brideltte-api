-- Brideltte Database Schema (for shared hosting - import via phpMyAdmin)
-- Select your database first in phpMyAdmin, then import this file

-- =============================================
-- USERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(500) DEFAULT NULL,
    role ENUM('user', 'vendor', 'admin') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- VENDORS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    business_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    website VARCHAR(500) DEFAULT NULL,
    price_range VARCHAR(50) NOT NULL,
    images JSON DEFAULT NULL,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    review_count INT NOT NULL DEFAULT 0,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vendors_category (category),
    INDEX idx_vendors_rating (rating),
    INDEX idx_vendors_verified (verified),
    INDEX idx_vendors_active (is_active),
    CONSTRAINT fk_vendors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SERVICES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_services_vendor (vendor_id),
    INDEX idx_services_available (available),
    CONSTRAINT fk_services_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BOOKINGS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    service_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_time VARCHAR(20) NOT NULL,
    location VARCHAR(255) NOT NULL,
    guest_count INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    notes TEXT DEFAULT NULL,
    cancel_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bookings_user (user_id),
    INDEX idx_bookings_vendor (vendor_id),
    INDEX idx_bookings_status (status),
    INDEX idx_bookings_date (event_date),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- REVIEWS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    booking_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment TEXT NOT NULL,
    images JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reviews_vendor (vendor_id),
    INDEX idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FAVORITES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_favorites_user_vendor (user_id, vendor_id),
    INDEX idx_favorites_user (user_id),
    INDEX idx_favorites_vendor (vendor_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CONTACT MESSAGES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contacts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA
-- =============================================

-- Admin user (password: password)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@brideltte.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample vendor users (password: password)
INSERT INTO users (name, email, password, role) VALUES
('Ahmed Hassan', 'ahmed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor'),
('Sara Mohamed', 'sara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor'),
('Omar Ali', 'omar@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor');

-- Sample regular user (password: password)
INSERT INTO users (name, email, password, role) VALUES
('Nour Ibrahim', 'nour@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Sample vendors (user_id 2=Ahmed, 3=Sara, 4=Omar)
INSERT INTO vendors (user_id, business_name, category, description, location, phone, email, website, price_range, images, rating, review_count, verified) VALUES
(2, 'Royal Palace Hall', 'Venues', 'A luxurious wedding venue with stunning architecture, crystal chandeliers, and capacity for up to 500 guests.', 'Cairo, Egypt', '+201234567890', 'info@royalpalace.com', 'https://royalpalace.com', '$$$', '[]', 4.80, 24, 1),
(3, 'Golden Lens Photography', 'Photography', 'Award-winning wedding photography studio specializing in candid moments and artistic portraits.', 'Alexandria, Egypt', '+201098765432', 'hello@goldenlens.com', 'https://goldenlens.com', '$$', '[]', 4.90, 31, 1),
(4, 'Elegant Events Catering', 'Catering', 'Premium catering service offering diverse cuisines from traditional Egyptian to international gourmet.', 'Giza, Egypt', '+201555666777', 'book@elegantevents.com', NULL, '$$-$$$', '[]', 4.50, 18, 1);

-- Sample services
INSERT INTO services (vendor_id, name, description, price, duration, category, available) VALUES
(1, 'Grand Ballroom Package', 'Full ballroom rental with decoration, lighting, and sound system for up to 500 guests', 5000.00, '8 hours', 'Venues', 1),
(1, 'Garden Ceremony Package', 'Beautiful outdoor garden ceremony setup with seating and arch decoration', 3000.00, '4 hours', 'Venues', 1),
(1, 'VIP Lounge Package', 'Exclusive VIP lounge area with premium service for up to 50 guests', 2000.00, '6 hours', 'Venues', 1),
(2, 'Full Day Photography', 'Complete wedding day coverage from preparation to reception with 2 photographers', 1500.00, '12 hours', 'Photography', 1),
(2, 'Half Day Photography', 'Ceremony and reception coverage with 1 photographer', 800.00, '6 hours', 'Photography', 1),
(2, 'Pre-Wedding Photoshoot', 'Engagement or pre-wedding photoshoot at location of your choice', 500.00, '3 hours', 'Photography', 1),
(3, 'Premium Buffet (per person)', 'Full buffet with 5 main courses, salads, desserts, and beverages', 45.00, 'Per person', 'Catering', 1),
(3, 'Standard Menu (per person)', '3 main courses with sides, dessert, and soft drinks', 30.00, 'Per person', 'Catering', 1);

-- Sample bookings
INSERT INTO bookings (user_id, vendor_id, service_id, event_date, event_time, location, guest_count, status, total_price, notes) VALUES
(5, 1, 1, '2026-04-15', '18:00', 'Royal Palace Hall, Cairo', 200, 'confirmed', 5000.00, 'Need extra lighting for the stage area'),
(5, 2, 4, '2026-04-15', '10:00', 'Cairo, Egypt', 200, 'pending', 1500.00, 'Please include drone shots'),
(5, 3, 7, '2026-04-15', '19:00', 'Royal Palace Hall, Cairo', 200, 'completed', 9000.00, NULL);

-- Sample reviews
INSERT INTO reviews (user_id, vendor_id, booking_id, rating, comment) VALUES
(5, 3, 3, 5, 'Amazing food and service! The buffet was incredible and all guests were satisfied. Highly recommended!');
