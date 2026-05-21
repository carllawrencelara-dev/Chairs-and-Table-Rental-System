<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load database connection FIRST
require_once 'config/database.php';
require_once 'includes/functions.php';

$bookingMessage = '';
$bookingType = '';
$showReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $name = sanitize($_POST['name']);
    $contact = sanitize($_POST['contact']);
    $date = sanitize($_POST['date']);
    $time = sanitize($_POST['time']);
    $location = sanitize($_POST['location']);
    $eventType = !empty($_POST['event_type']) ? sanitize($_POST['event_type']) : '';
    $chairs = (int)$_POST['chairs'];
    $tables = (int)$_POST['tables'];
    $distance = (float)$_POST['distance'];
    $deliveryType = sanitize($_POST['delivery_type']);
    $agree = isset($_POST['agree']) ? 1 : 0;
    
    $errors = [];
    
    if ($name && !preg_match('/^[A-Za-z]/', $name)) {
    $errors[] = 'Full name must start with a letter';
    }

    if (!$contact) $errors[] = 'Contact number is required';
        if ($contact && !preg_match('/^09[0-9]{9}$/', $contact)) {
    $errors[] = 'Contact number must start with 09 and be 11 digits total';
    }
    
    if (!$date) $errors[] = 'Event date is required';
    if (!$time) $errors[] = 'Event time is required';
    if (!$location) $errors[] = 'Venue location is required';
    if ($chairs === 0 && $tables === 0) $errors[] = 'Please select at least 1 chair or table';
    if (!$agree) $errors[] = 'You must agree to the terms and conditions';

    
    
    $availability = getAvailability($date);
    if ($chairs > $availability['available_chairs']) $errors[] = 'Not enough chairs available';
    if ($tables > $availability['available_tables']) $errors[] = 'Not enough tables available';
    
    $idPath = null;
$uploadErrors = [];

if (isset($_FILES['valid_id'])) {
    $file = $_FILES['valid_id'];
    
    // Check if file was uploaded without errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        
        // 1. Check file size (max 5MB)
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxFileSize) {
            $uploadErrors[] = 'File is too large. Maximum size is 5MB.';
        }
        
        // 2. Check file type (only images and PDFs)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $uploadErrors[] = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
        }
        
        // 3. Check for fake image (optional but good)
        if (strpos($mimeType, 'image/') === 0) {
            $checkImage = getimagesize($file['tmp_name']);
            if ($checkImage === false) {
                $uploadErrors[] = 'Uploaded file is not a valid image.';
            }
        }
        
        // If no errors, proceed with upload
        if (empty($uploadErrors)) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate safe filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                $idPath = $uploadFile;
            } else {
                $uploadErrors[] = 'Failed to save uploaded file. Please try again.';
            }
        }
        
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $uploadErrors[] = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $uploadErrors[] = 'File was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $uploadErrors[] = 'Server error: Temporary folder missing.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $uploadErrors[] = 'Server error: Failed to write file.';
                break;
            default:
                $uploadErrors[] = 'Unknown upload error occurred.';
        }
    }
    
    // Add any upload errors to the main errors array
    if (!empty($uploadErrors)) {
        $errors = array_merge($errors, $uploadErrors);
    }
}

   
    
    if (empty($errors)) {
        $costs = calculateCosts($chairs, $tables, $distance, $deliveryType);
        $ref = generateBookingRef();
        $pendingStatus = 'pending';
        
        $sql = "INSERT INTO bookings (booking_ref, customer_name, customer_contact, event_date, event_time, venue_location, event_type, chairs, tables, delivery_type, distance_km, chair_cost, table_cost, labor_cost, fuel_cost, total_amount, id_file_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssiiiddddddss", 
            $ref, $name, $contact, $date, $time, $location, $eventType,
            $chairs, $tables, $deliveryType, $distance,
            $costs['chair_cost'], $costs['table_cost'], $costs['labor_cost'], $costs['fuel_cost'], $costs['total'],
            $idPath, $pendingStatus
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $bookingMessage = 'Booking submitted successfully! Reference: ' . $ref;
            $bookingType = 'success';
            $showReceipt = [
                'ref' => $ref,
                'name' => $name,
                'contact' => $contact,
                'date' => $date,
                'time' => $time,
                'location' => $location,
                'event_type' => $eventType,
                'chairs' => $chairs,
                'tables' => $tables,
                'delivery_type' => $deliveryType,
                'distance' => $distance,
                'chair_cost' => $costs['chair_cost'],
                'table_cost' => $costs['table_cost'],
                'labor_cost' => $costs['labor_cost'],
                'fuel_cost' => $costs['fuel_cost'],
                'total' => $costs['total']
            ];
        } else {
            $bookingMessage = 'Failed to submit booking: ' . mysqli_error($conn);
            $bookingType = 'danger';
        }
    } else {
        $bookingMessage = implode(', ', $errors);
        $bookingType = 'warning';
    }
}

$stats = getDashboardStats();
?>
<?php include 'includes/header.php'; ?>

<!-- Navigation -->
<nav class="navbar">
<div class="logo" onclick="window.location.href = window.location.pathname;">
        <div class="logo-icon"><i class="fas fa-chair"></i></div>
        <div class="logo-text">
            <h1>CT RENTAL</h1>
            <p>Tables & Chairs</p>
        </div>
    </div>
    <div class="nav-links">
        <button class="btn-nav" onclick="document.getElementById('booking-section').scrollIntoView({behavior:'smooth'})">Book Now</button>
        <button class="btn-nav" onclick="openStatusModal()">Check Booking Status</button>
        <button class="btn-nav" onclick="window.location.href='admin.php'">Admin Portal</button>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-star"></i> Sogod's Most Trusted Rental Partner
        </div>
        <h1>Premium Tables & <span>Chairs</span><br>For Your Special Events</h1>
        <p>From intimate gatherings to grand celebrations — CT Rental delivers quality furniture with reliable service across Sogod and surrounding areas.</p>
        <div class="hero-actions">
            <button class="btn-primary" onclick="document.getElementById('booking-section').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-calendar-check"></i> Book Now — Free Estimate
            </button>
            <button class="btn-outline" onclick="document.getElementById('trust-section').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-trophy"></i> Our Accomplishments
            </button>
        </div>
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($stats['available_chairs']); ?></div>
                <div class="stat-label">Chairs Available</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($stats['available_tables']); ?></div>
                <div class="stat-label">Tables Available</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">₱30</div>
                <div class="stat-label">Per Chair</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">₱130</div>
                <div class="stat-label">Per Table</div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Section -->
<section id="trust-section" class="trust-section">
    <div class="trust-card">
        <div class="trust-rating">95%</div>
        <div class="trust-label">Customer Satisfaction Rating</div>
        <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">Based on 2,000+ completed events across Sogod</div>
        <div class="trust-grid">
            <div class="trust-item"><i class="fas fa-calendar-check"></i><strong>2,400+</strong><span>Events Completed</span></div>
            <div class="trust-item"><i class="fas fa-users"></i><strong>1,800+</strong><span>Happy Clients</span></div>
            <div class="trust-item"><i class="fas fa-star"></i><strong>4.9/5.0</strong><span>Average Rating</span></div>
            <div class="trust-item"><i class="fas fa-clock"></i><strong>99%</strong><span>On-Time Delivery</span></div>
            <div class="trust-item"><i class="fas fa-shield-alt"></i><strong>5+ Years</strong><span>In Business</span></div>
            <div class="trust-item"><i class="fas fa-trophy"></i><strong>25+</strong><span>Industry Awards</span></div>
            <div class="trust-item"><i class="fas fa-building"></i><strong>150+</strong><span>Corporate Partners</span></div>
            <div class="trust-item"><i class="fas fa-handshake"></i><strong>100%</strong><span>Satisfaction Guarantee</span></div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="pricing-section">
    <h2 class="section-title">Simple, Transparent Pricing</h2>
    <p class="section-subtitle">No hidden fees — labor and delivery costs computed automatically</p>
    <div class="pricing-grid">
        <div class="pricing-card">
            <i class="fas fa-chair"></i>
            <h3>Monobloc Chair</h3>
            <div class="price">₱30 <span>/each</span></div>
            <p class="price-desc">Durable, stackable plastic chairs perfect for any event</p>
        </div>
        <div class="pricing-card">
            <i class="fas fa-table"></i>
            <h3>Folding Table</h3>
            <div class="price">₱130 <span>/each</span></div>
            <p class="price-desc">6ft rectangular, foldable steel-leg tables</p>
        </div>
        <div class="pricing-card">
            <i class="fas fa-truck"></i>
            <h3>Delivery Fee</h3>
            <div class="price" style="font-size: 1.5rem;">₱50 Within Sogod</div>
            <p class="price-desc">Based on distance from our warehouse</p>
        </div>
        <div class="pricing-card">
            <i class="fas fa-briefcase"></i>
            <h3>Labor Cost</h3>
            <div class="price" style="font-size: 1.5rem;">₱50/100 items</div>
            <p class="price-desc">Setup and arrangement assistance</p>
        </div>
    </div>
</section>

<!-- Booking Form -->
<section id="booking-section" class="booking-section">
    <div class="form-container">
        <div class="logo" style="justify-content: center; margin-bottom: 1.5rem;">
            <div class="logo-icon"><i class="fas fa-chair"></i></div>
            <div class="logo-text">
                <h1 style="font-size: 1.2rem;">CT RENTAL</h1>
                <p>Book Your Rental</p>
            </div>
        </div>
        
        <?php if ($bookingMessage): ?>
            <div class="alert alert-<?php echo $bookingType; ?>"><?php echo $bookingMessage; ?></div>
        <?php endif; ?>
        
<form method="POST" enctype="multipart/form-data" autocomplete="off">
    <!-- Row 1: Name and Contact -->
    <div class="form-row">
        <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
<input type="text" name="name" id="full-name" required pattern="[A-Za-z][A-Za-z\s]*" title="Full name must start with a letter and contain only letters and spaces" autocomplete="off" data-lpignore="true"><small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 5px;">Must start with a letter (e.g., Juan Dela Cruz)</small>        </div>
        <div class="form-group">
            <label>Contact Number <span class="required">*</span></label>
<input type="tel" name="contact" id="contact-number" placeholder="09123456789" required maxlength="11" pattern="09[0-9]{9}" autocomplete="off" data-lpignore="true">
            <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 5px;">Example: 09123456789 (Start with 09, 11 digits)</small>
        </div>
    </div>
    
    <!-- Row 2: Event Date and Time -->
    <div class="form-row">
        <div class="form-group">
            <label>Event Date <span class="required">*</span></label>
            <input type="date" name="date" id="event-date" required>
        </div>
        <div class="form-group">
            <label>Event Time <span class="required">*</span></label>
<select name="time" id="event-time" class="form-select" required style="width: 100%; padding: 0.8rem 1rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary);">
                <option value="">Select Time</option>
                <option value="08:00">08:00 AM</option>
                <option value="09:00">09:00 AM</option>
                <option value="10:00">10:00 AM</option>
                <option value="11:00">11:00 AM</option>
                <option value="12:00">12:00 PM</option>
                <option value="13:00">01:00 PM</option>
                <option value="14:00">02:00 PM</option>
                <option value="15:00">03:00 PM</option>
                <option value="16:00">04:00 PM</option>
                <option value="17:00">05:00 PM</option>
                <option value="18:00">06:00 PM</option>
                <option value="19:00">07:00 PM</option>
                <option value="20:00">08:00 PM</option>
            </select>
        </div>
    </div>
    
    <!-- Row 3: Venue Location (Full width) -->
    <div class="form-group">
        <label>Venue / Specific Location <span class="required">*</span></label>
        <input type="text" name="location" placeholder="Full address of event venue" required>
    </div>
    
    <!-- Row 4: Delivery Location and Event Type -->
    <div class="form-row">
        <div class="form-group">
            <label>Delivery Location <span class="required">*</span></label>
            <select name="location_area" id="location-area" required style="width: 100%; padding: 0.8rem 1rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary);">
                <option value="">Select Location</option>
                <option value="Sogod" data-fee="50">Sogod</option>
                <option value="Bontoc" data-fee="100">Bontoc</option>
                <option value="Divisorya" data-fee="100">Divisorya</option>
                <option value="Libagon" data-fee="100">Libagon</option>
                <option value="Tomas Oppus" data-fee="100">Tomas Oppus</option>
                <option value="Malitbog" data-fee="150">Malitbog</option>
                <option value="Other" data-fee="200">Other Location</option>
            </select>
            <input type="hidden" name="distance" id="distance-input" value="0">
            <small id="delivery-fee-display" style="color: var(--accent); display: block; margin-top: 5px;">Delivery Fee: ₱0</small>
        </div>
        <div class="form-group">
            <label>Event Type <span style="color: var(--text-muted);">(Optional)</span></label>
            <input type="text" name="event_type" list="event-types" placeholder="Select or type event type..." style="width: 100%; padding: 0.8rem 1rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary);">
<datalist id="event-types">
    <option value="Wedding">
    <option value="Birthday Party">
    <option value="Corporate Event">
    <option value="Fiesta / Community">
    <option value="Graduation">
    <option value="Funeral / Wake">
    <option value="Reunion">
    <option value="Other">
</datalist>
        </div>
    </div>
    
    <!-- Row 5: Valid ID Upload (Full width) -->
    <div class="form-group">
    <label>Upload Valid ID <span class="required">*</span> <span style="color: var(--text-muted); font-size: 0.75rem;">(For verification)</span></label>
    <input type="file" name="valid_id" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
    <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 5px;">
        <i class="fas fa-info-circle"></i> Allowed: JPG, PNG, PDF only. Max size: 5MB
    </small>
</div>
    
    <!-- Row 6: Quantity Selection -->
    <div class="form-row">
        <div class="form-group">
            <label>Chairs (₱30 each)</label>
            <div class="quantity-selector">
                <input type="number" class="qty-input" id="qty-chairs" value="0" min="0" onchange="manualUpdate('chairs')">
            </div>
            <input type="hidden" name="chairs" id="input-chairs" value="0">
            <div class="stock-info" id="chair-avail" data-available="<?php echo $stats['available_chairs']; ?>">
                Available: <?php echo number_format($stats['available_chairs']); ?>
            </div>
        </div>
        <div class="form-group">
            <label>Tables (₱130 each)</label>
            <div class="quantity-selector">
                <input type="number" class="qty-input" id="qty-tables" value="0" min="0" onchange="manualUpdate('tables')">
            </div>
            <input type="hidden" name="tables" id="input-tables" value="0">
            <div class="stock-info" id="table-avail" data-available="<?php echo $stats['available_tables']; ?>">
                Available: <?php echo number_format($stats['available_tables']); ?>
            </div>
        </div>
    </div>
    
    <!-- Row 7: Logistics Toggle (Full width) -->
    <div class="form-group">
        <label>Logistics</label>
        <div class="toggle-group">
    <button type="button" class="toggle-btn active" id="toggle-delivery" onclick="setDeliveryType('delivery')">
        <i class="fas fa-truck"></i> Company Delivery
    </button>
    <button type="button" class="toggle-btn" id="toggle-pickup" onclick="setDeliveryType('pickup')">
        <i class="fas fa-store"></i> Customer Pick-up
    </button>
</div>
<input type="hidden" name="delivery_type" id="input-delivery-type" value="delivery">
    </div>
    
    <!-- Row 8: Price Summary -->
    <div class="price-summary">
        <div class="price-summary-title">Cost Summary</div>
        <div class="price-row">
            <span>Chairs (<span id="summary-chairs">0</span> × ₱30)</span>
            <span>₱<span id="summary-chair-cost">0</span></span>
        </div>
        <div class="price-row">
            <span>Tables (<span id="summary-tables">0</span> × ₱130)</span>
            <span>₱<span id="summary-table-cost">0</span></span>
        </div>
        <div class="price-row" id="labor-row">
            <span>Labor Cost</span>
            <span>₱<span id="summary-labor">0</span></span>
        </div>
        <div class="price-row" id="fuel-row">
            <span>Delivery Fee</span>
            <span>₱<span id="summary-fuel">0</span></span>
        </div>
        <div class="price-row total">
            <span>Total Estimate</span>
            <span>₱<span id="summary-total">0</span></span>
        </div>
    </div>
    
    <!-- Row 9: Agreement Checkbox -->
    <div class="agree-checkbox">
        <input type="checkbox" name="agree" id="agree-terms" required>
        <label for="agree-terms">
            I agree to CT Rental's terms and conditions. I acknowledge my liability for any damages, 
            missing items, or incomplete returns. I understand CT Rental is not responsible for injuries 
            caused by improper use of rented equipment.
        </label>
    </div>
    
    <!-- Row 10: Submit Button -->
    <button type="submit" name="submit_booking" class="btn-primary submit-btn">
        <i class="fas fa-paper-plane"></i> Submit Booking Request
    </button>
</form>
        
        <div class="contact-footer">
            <i class="fas fa-phone"></i> <?php echo SITE_PHONE; ?> &nbsp;|&nbsp;
            <i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="footer-logo">
        <div class="logo" style="justify-content: center;">
            <div class="logo-icon"><i class="fas fa-chair"></i></div>
            <div class="logo-text"><h1 style="font-size: 1.2rem;">CT RENTAL</h1></div>
        </div>
    </div>
    <div class="footer-contact">
        <p><i class="fas fa-phone"></i> <?php echo SITE_PHONE; ?></p>
        <p><i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?></p>
        <p><i class="fas fa-map-marker-alt"></i> Sogod, Southern Leyte</p>
    </div>
    <div class="copyright"><p>&copy; <?php echo date('Y'); ?> CT Rental. All rights reserved.</p></div>
</footer>

<!-- Receipt Modal -->
<?php if ($showReceipt): ?>
<div class="modal-overlay show" id="receipt-modal">
    <div class="receipt">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
            <button onclick="closeReceiptModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #999; line-height: 1;">&times;</button>
        </div>
        
        <div class="receipt-header">
            <div class="receipt-logo"><i class="fas fa-chair"></i></div>
            <div class="receipt-title">CT RENTAL</div>
            <div style="font-size: 0.8rem; color: #666;">Tables & Chairs Rental</div>
        </div>
        
        <!-- PROMINENT REFERENCE NUMBER SECTION -->
        <div class="receipt-ref">
            <div class="receipt-ref-label">BOOKING REFERENCE NUMBER</div>
            <div class="receipt-ref-number"><?php echo $showReceipt['ref']; ?></div>
            <div class="receipt-ref-note">Please save this reference number to check your booking status</div>
        </div>
        
        <div class="receipt-row"><span>Customer Name:</span><span><?php echo $showReceipt['name']; ?></span></div>
        <div class="receipt-row"><span>Contact:</span><span><?php echo $showReceipt['contact']; ?></span></div>
        <div class="receipt-row"><span>Event Date:</span><span><?php echo formatDate($showReceipt['date']); ?></span></div>
        <div class="receipt-row"><span>Event Time:</span><span><?php echo $showReceipt['time']; ?></span></div>
        <div class="receipt-row"><span>Venue:</span><span><?php echo $showReceipt['location']; ?></span></div>
        <?php if ($showReceipt['event_type']): ?>
        <div class="receipt-row"><span>Event Type:</span><span><?php echo $showReceipt['event_type']; ?></span></div>
        <?php endif; ?>
        <div class="receipt-row"><span>Chairs:</span><span><?php echo $showReceipt['chairs']; ?> × ₱30</span></div>
        <div class="receipt-row"><span>Tables:</span><span><?php echo $showReceipt['tables']; ?> × ₱130</span></div>
        <div class="receipt-row"><span>Logistics:</span><span><?php echo $showReceipt['delivery_type'] === 'delivery' ? 'Company Delivery' : 'Customer Pick-up'; ?></span></div>
        <?php if ($showReceipt['delivery_type'] === 'delivery'): ?>
        <div class="receipt-row"><span>Distance:</span><span><?php echo $showReceipt['distance']; ?> km</span></div>
        <div class="receipt-row"><span>Labor Cost:</span><span>₱<?php echo number_format($showReceipt['labor_cost']); ?></span></div>
        <div class="receipt-row"><span>Fuel Fee:</span><span>₱<?php echo number_format($showReceipt['fuel_cost']); ?></span></div>
        <?php endif; ?>
        <div class="receipt-row receipt-total"><span>TOTAL AMOUNT:</span><span>₱<?php echo number_format($showReceipt['total']); ?></span></div>
        
        <div class="receipt-footer">
            <p>Thank you for choosing CT Rental!</p>
            <p>Our team will contact you to confirm your booking.</p>
        </div>
        <div class="receipt-actions">
            <button class="btn-primary btn-print" onclick="printReceipt()" style="flex: 1;"><i class="fas fa-print"></i> Print</button>
            <button class="btn-outline" onclick="closeReceiptModal()" style="flex: 1;">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Status Check Modal -->
<div id="statusModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-search"></i> Check Booking Status</h3>
            <button onclick="closeStatusModal()">&times;</button>
        </div>
        <div id="statusSearchForm">
            <div class="form-group">
                <label>Enter Your Booking Reference Number</label>
                <input type="text" id="bookingRef" placeholder="Example: CTR-2025-0001">
            </div>
            <button onclick="checkBookingStatus()" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
    <i class="fas fa-search"></i> Check Status
</button>
        </div>
        <div id="statusResult" style="display: none;"></div>
    </div>
</div>

<style>
/* Black background for dropdown options */
select option {
    background: #1a1a1a !important;
    color: white !important;
}

/* For the dropdown list background */
select {
    background: #1a1a1a;
    color: white;
}

/* For when dropdown is open */
select:focus {
    background: #1a1a1a;
}

/* For the select dropdown menu */
select.dropdown-menu {
    background: black;
}

/* Make all dropdowns glow green when clicked */
select:focus,
input:focus,
input[type="text"]:focus,
input[type="tel"]:focus,
input[type="date"]:focus {
    border-color: #4ade80 !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.3) !important;
    background: rgba(74, 222, 128, 0.05) !important;
}

/* Specifically for event time dropdown */
#event-time:focus {
    border-color: #4ade80 !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.3) !important;
}

/* Specifically for location dropdown */
#location-area:focus {
    border-color: #4ade80 !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.3) !important;
}

/* For event type input (since you changed it to input) */
input[name="event_type"]:focus {
    border-color: #4ade80 !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.3) !important;
}

.form-container {
    max-width: 800px;
    margin: 0 auto;
    background: var(--dark-surface);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid var(--border);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group.full-width {
    grid-column: span 2;
}

.price-summary {
    background: var(--dark-card);
    border-radius: 12px;
    padding: 1rem;
    margin: 1rem 0;
}

.price-summary-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    color: var(--text-secondary);
}

.price-row.total {
    border-top: 1px solid var(--border);
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    font-weight: 700;
    color: var(--accent);
    font-size: 1.1rem;
}

.agree-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1rem 0;
}

.agree-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.agree-checkbox label {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.submit-btn {
    width: 100%;
    justify-content: center;
}

.contact-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    font-size: 0.8rem;
    color: var(--text-muted);
}

.stock-info {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.toggle-group {
    display: flex;
    gap: 1rem;
}

.toggle-btn {
    flex: 1;
    padding: 0.7rem;
    background: var(--dark-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-btn.active {
    background: var(--primary);
    border-color: var(--accent);
    color: white;
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--dark-card);
    padding: 0.5rem;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.qty-btn {
    width: 36px;
    height: 36px;
    background: var(--dark-surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    font-size: 1rem;
}

.qty-value {
    font-size: 1.2rem;
    font-weight: 600;
    min-width: 50px;
    text-align: center;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal-content {
    max-width: 500px;
    width: 90%;
    background: var(--dark-surface);
    border-radius: 16px;
    padding: 1.5rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.modal-header button {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
}

.receipt {
    background: white;
    color: #1a1a1a;
    border-radius: 16px;
    padding: 1.5rem;
    max-width: 500px;
    width: 90%;
}

.receipt-header {
    text-align: center;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e5e5;
    margin-bottom: 1rem;
}

.receipt-logo {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #1a4a2a, #2d6a3b);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1.5rem;
}

.receipt-ref {
    background: #f0f0f0;
    padding: 0.5rem;
    text-align: center;
    border-radius: 8px;
    font-family: monospace;
    margin: 1rem 0;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e5e5e5;
    font-size: 0.85rem;
}

.receipt-total {
    font-weight: 700;
    font-size: 1rem;
    color: #1a4a2a;
}

.receipt-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e5e5;
    font-size: 0.7rem;
    color: #666;
    text-align: center;
}

.receipt-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-print {
    background: #1a4a2a;
    color: white;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

select {
    background: var(--dark-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.8rem 1rem;
    color: var(--text-primary);
    cursor: pointer;
}

select:focus {
    border-color: var(--accent);
    background: rgba(74, 222, 128, 0.05);
    outline: none;
}

.form-select:focus {
    border-color: #4ade80;
    background: rgba(74, 222, 128, 0.1);
    outline: none;
}

datalist {
    background: black;
    color: white;
}

input::-webkit-calendar-picker-indicator {
    background-color: #333;
    border-radius: 5px;
    padding: 5px;
    cursor: pointer;

    select:focus, input:focus, datalist:focus {
    border-color: var(--accent) !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.2) !important;
    background: rgba(74, 222, 128, 0.05);
}

/* Event Date Picker - Black background, white text */
input[type="date"] {
    background: var(--dark-card);
    color: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.8rem 1rem;
}

/* For the calendar picker icon */
input[type="date"]::-webkit-calendar-picker-indicator {
    background-color: #333;
    border-radius: 5px;
    padding: 5px;
    cursor: pointer;
    filter: invert(1);
}

/* For Firefox */
input[type="date"]::-moz-calendar-picker-indicator {
    background-color: #333;
    border-radius: 5px;
    padding: 5px;
    cursor: pointer;
}

#event-date {
    background: var(--dark-card);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

#event-date:focus {
    border-color: var(--accent);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.2);
}
</style>


<script>

    
let quantities = { chairs: 0, tables: 0 };
let deliveryType = 'delivery';

function updateQuantity(type, delta) {
    let max = type === 'chairs' ? parseInt(document.getElementById('chair-avail').dataset.available || 1500) : parseInt(document.getElementById('table-avail').dataset.available || 500);
    let newValue = quantities[type] + delta;
    newValue = Math.max(0, Math.min(newValue, max));
    quantities[type] = newValue;
    document.getElementById('qty-' + type).value = quantities[type];
    document.getElementById('input-' + type).value = quantities[type];
    updatePriceSummary();
}

function manualUpdate(type) {
    let input = document.getElementById('qty-' + type);
    let max = type === 'chairs' ? parseInt(document.getElementById('chair-avail').dataset.available || 1500) : parseInt(document.getElementById('table-avail').dataset.available || 500);
    let newValue = parseInt(input.value) || 0;
    newValue = Math.max(0, Math.min(newValue, max));
    quantities[type] = newValue;
    input.value = quantities[type];
    document.getElementById('input-' + type).value = quantities[type];
    updatePriceSummary();
}

function setDeliveryType(type) {
    deliveryType = type;
    var deliveryBtn = document.getElementById('toggle-delivery');
    var pickupBtn = document.getElementById('toggle-pickup');
    var distanceInput = document.getElementById('distance-input');
    
    if (deliveryBtn) deliveryBtn.classList.toggle('active', type === 'delivery');
    if (pickupBtn) pickupBtn.classList.toggle('active', type === 'pickup');
    document.getElementById('input-delivery-type').value = type;
    
    if (type === 'pickup') {
        if (distanceInput) {
            distanceInput.disabled = true;
            // Don't set value to 0 - keep the original data
            // distanceInput.value = 0;  ← REMOVED THIS LINE
        }
        // Disable location dropdown but keep the value
        var locationSelect = document.getElementById('location-area');
        if (locationSelect) {
            locationSelect.disabled = true;
            // Don't clear the value - keep it
            // locationSelect.value = '';  ← REMOVED THIS LINE
        }
        var feeDisplay = document.getElementById('delivery-fee-display');
        if (feeDisplay) feeDisplay.innerHTML = 'Delivery Fee: ₱0 (Pickup)';
    } else {
        if (distanceInput) {
            distanceInput.disabled = false;
        }
        var locationSelect = document.getElementById('location-area');
        if (locationSelect) {
            locationSelect.disabled = false;
        }
        updateDeliveryFee();
    }
    
    updatePriceSummary();
}

function updatePriceSummary() {
    var chairs = quantities.chairs;
    var tables = quantities.tables;
    var distance = parseFloat(document.getElementById('distance-input').value) || 0;
    var chairCost = chairs * 30;
    var tableCost = tables * 130;
    var items = chairs + tables;
    var laborCost = 0, fuelCost = 0;
    
    if (deliveryType === 'delivery') {
laborCost = Math.ceil(items / 100) * 50;
        fuelCost = distance;
    }
    
    var total = chairCost + tableCost + laborCost + fuelCost;
    document.getElementById('summary-chairs').textContent = chairs;
    document.getElementById('summary-tables').textContent = tables;
    document.getElementById('summary-chair-cost').textContent = chairCost.toLocaleString();
    document.getElementById('summary-table-cost').textContent = tableCost.toLocaleString();
    document.getElementById('summary-labor').textContent = laborCost.toLocaleString();
    document.getElementById('summary-fuel').textContent = fuelCost.toLocaleString();
    document.getElementById('summary-total').textContent = total.toLocaleString();
}

function closeReceiptModal() {
    var modal = document.getElementById('receipt-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
}

function printReceipt() {
    window.print();
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
}

function openStatusModal() { 
    document.getElementById('statusModal').style.display = 'flex'; 
    document.getElementById('statusSearchForm').style.display = 'block'; 
    document.getElementById('statusResult').style.display = 'none'; 
    document.getElementById('statusResult').innerHTML = ''; 
}

function closeStatusModal() { 
    var modal = document.getElementById('statusModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Clear the search form and result
    var searchForm = document.getElementById('statusSearchForm');
    var resultDiv = document.getElementById('statusResult');
    var bookingRef = document.getElementById('bookingRef');
    
    if (searchForm) {
        searchForm.style.display = 'block';
    }
    if (resultDiv) {
        resultDiv.style.display = 'none';
        resultDiv.innerHTML = '';
    }
    if (bookingRef) {
        bookingRef.value = '';
    }
}

function resetStatusSearch() { 
    document.getElementById('statusSearchForm').style.display = 'block'; 
    document.getElementById('statusResult').style.display = 'none'; 
    document.getElementById('statusResult').innerHTML = ''; 
    document.getElementById('bookingRef').value = ''; 
}

function checkBookingStatus() {
    var ref = document.getElementById('bookingRef').value.trim();
    if (!ref) { 
        alert('Please enter your booking reference number'); 
        return; 
    }
    var resultDiv = document.getElementById('statusResult');
    resultDiv.innerHTML = '<div class="alert alert-info" style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Checking status...</div>';
    resultDiv.style.display = 'block';
    document.getElementById('statusSearchForm').style.display = 'none';
    
    fetch('/ct/ct-rental-2/api/get_booking.php?ref=' + encodeURIComponent(ref))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) { 
                displayStatusResult(data.booking); 
            } else { 
                resultDiv.innerHTML = '<div class="alert alert-danger" style="text-align: center;">' + data.message + '</div><div style="text-align: center; margin-top: 1rem;"><button onclick="resetStatusSearch()" class="btn-outline">Try Again</button></div>'; 
            }
        })
        .catch(function(error) { 
            resultDiv.innerHTML = '<div class="alert alert-danger" style="text-align: center;">Error connecting to server.</div><div style="text-align: center; margin-top: 1rem;"><button onclick="resetStatusSearch()" class="btn-outline">Try Again</button></div>'; 
        });
}

function displayStatusResult(booking) {
    var statusColors = { 
        'pending': 'status-pending', 
        'accepted': 'status-accepted', 
        'completed': 'status-completed', 
        'cancelled': 'status-cancelled' 
    };
    var statusText = { 
        'pending': 'Pending Review', 
        'accepted': 'Accepted - Confirmed', 
        'completed': 'Completed', 
        'cancelled': 'Cancelled' 
    };
    var statusIcon = booking.status === 'pending' ? '<i class="fas fa-clock"></i>' : (booking.status === 'accepted' ? '<i class="fas fa-check-circle"></i>' : (booking.status === 'completed' ? '<i class="fas fa-flag-checkered"></i>' : '<i class="fas fa-times-circle"></i>'));
    
    var deliveryHtml = '';
    if (booking.delivery_type === 'delivery') {
        deliveryHtml = '<div class="status-row"><span class="status-label">Delivery Fee:</span><span class="status-value">₱' + parseFloat(booking.fuel_cost).toLocaleString() + '</span></div>' +
                       '<div class="status-row"><span class="status-label">Labor Cost:</span><span class="status-value">₱' + parseFloat(booking.labor_cost).toLocaleString() + '</span></div>';
    }
    
    document.getElementById('statusResult').innerHTML = '<div class="status-card"><div style="text-align: center; margin-bottom: 1rem;"><span class="status-badge ' + statusColors[booking.status] + '" style="font-size: 1rem; padding: 0.5rem 1rem;">' + statusIcon + ' ' + statusText[booking.status] + '</span></div>' +
        '<div class="status-row"><span class="status-label">Reference:</span><span class="status-value"><strong>' + booking.booking_ref + '</strong></span></div>' +
        '<div class="status-row"><span class="status-label">Customer:</span><span class="status-value">' + booking.customer_name + '</span></div>' +
        '<div class="status-row"><span class="status-label">Event Date:</span><span class="status-value">' + booking.event_date + '</span></div>' +
        '<div class="status-row"><span class="status-label">Venue:</span><span class="status-value">' + booking.venue_location + '</span></div>' +
        '<div class="status-row"><span class="status-label">Chairs:</span><span class="status-value">' + booking.chairs + ' × ₱25</span></div>' +
        '<div class="status-row"><span class="status-label">Tables:</span><span class="status-value">' + booking.tables + ' × ₱120</span></div>' +
        deliveryHtml +
        '<div class="status-row total"><span class="status-label">Total:</span><span class="status-value" style="color: var(--accent);">₱' + parseFloat(booking.total_amount).toLocaleString() + '</span></div></div>' +
        '<div style="margin-top: 1rem; text-align: center;"><button onclick="downloadReceipt(\'' + booking.booking_ref + '\')" class="btn-outline"><i class="fas fa-download"></i> Download Receipt</button>' +
        '<button onclick="closeStatusModal()" class="btn-outline">Close</button></div>';
}

function downloadReceipt(ref) { 
    window.open('/ct/ct-rental-2/api/download_receipt.php?ref=' + encodeURIComponent(ref), '_blank'); 
}

// ADD THIS NEW FUNCTION - Delivery Fee from Location
function updateDeliveryFee() {
    var locationSelect = document.getElementById('location-area');
    var feeDisplay = document.getElementById('delivery-fee-display');
    var distanceInput = document.getElementById('distance-input');
    
    if (locationSelect && locationSelect.value) {
        var selectedOption = locationSelect.options[locationSelect.selectedIndex];
        var fee = parseInt(selectedOption.getAttribute('data-fee')) || 0;
        
        if (feeDisplay) {
            feeDisplay.innerHTML = 'Delivery Fee: ₱' + fee.toLocaleString();
        }
        if (distanceInput) {
            distanceInput.value = fee;
        }
        
        updatePriceSummary();
    } else {
        if (feeDisplay) {
            feeDisplay.innerHTML = 'Delivery Fee: ₱0';
        }
        if (distanceInput) {
            distanceInput.value = 0;
        }
    }
}

function restrictTimeInput() {
    var timeInput = document.getElementById('event-time');
    if (timeInput) {
        timeInput.addEventListener('change', function() {
            var time = this.value;
            if (time) {
                var minutes = parseInt(time.split(':')[1]);
                var hours = time.split(':')[0];
                if (minutes !== 0 && minutes !== 30) {
                    var roundedMinutes = minutes < 30 ? '00' : '30';
                    this.value = hours + ':' + roundedMinutes;
                    alert('Please select a time in 30-minute intervals (e.g., 9:00, 9:30, 10:00)');
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var distanceInput = document.getElementById('distance-input');
    if (distanceInput) distanceInput.addEventListener('input', updatePriceSummary);
    var dateInput = document.getElementById('event-date');
    if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];
    
    restrictTimeInput();
    
    // ADD THIS - Location dropdown handler
    var locationSelect = document.getElementById('location-area');
    if (locationSelect) {
        locationSelect.addEventListener('change', updateDeliveryFee);
        updateDeliveryFee();
    }
    
    // Disable location dropdown if pickup is default
    if (deliveryType === 'pickup') {
        var locSelect = document.getElementById('location-area');
        if (locSelect) locSelect.disabled = true;
    }
});


// Contact number validation
function validateContactNumber() {
    var contactInput = document.getElementById('contact-number');
    var errorMsg = document.getElementById('contact-error');
    
    if (contactInput) {
        var value = contactInput.value.trim();
        var pattern = /^09[0-9]{9}$/;  // Starts with 09, followed by 9 digits (total 11)
        
        if (value && !pattern.test(value)) {
            if (errorMsg) errorMsg.style.display = 'block';
            contactInput.setCustomValidity('Contact number must start with 09 and be 11 digits total');
            return false;
        } else {
            if (errorMsg) errorMsg.style.display = 'none';
            contactInput.setCustomValidity('');
            return true;
        }
    }
    return true;
}

// Add event listeners for real-time validation
document.addEventListener('DOMContentLoaded', function() {
    var contactInput = document.getElementById('contact-number');
    if (contactInput) {
        contactInput.addEventListener('input', validateContactNumber);
        contactInput.addEventListener('blur', validateContactNumber);
    }

});

// Contact number validation
var contactInput = document.getElementById('contact-number');
if (contactInput) {
    contactInput.addEventListener('input', function() {
        var value = this.value;
        var pattern = /^09[0-9]{9}$/;
        if (value && !pattern.test(value)) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '';
        }
    });
}

// Full name validation - must start with a letter
var fullNameInput = document.getElementById('full-name');
if (fullNameInput) {
    fullNameInput.addEventListener('input', function() {
        var value = this.value;
        var pattern = /^[A-Za-z]/;
        if (value && !pattern.test(value)) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '';
        }
    });
}

</script>

<?php include 'includes/footer.php'; ?>