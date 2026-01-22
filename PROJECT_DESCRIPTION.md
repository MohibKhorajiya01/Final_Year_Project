# Event Ease - Event Booking Management System

## 📋 Project Overview

**Event Ease** ek comprehensive Event Booking Management System hai jo PHP, MySQL, aur modern web technologies use karke banaya gaya hai. Ye system event planning, booking management, payment processing, aur multi-role user management provide karta hai.

---

## 🎯 Project Ka Main Purpose
   
Event Ease ek complete solution hai jo:
- **Users** ko events browse, book, aur manage karne ki facility deta hai
- **Managers** ko apne events create, manage, aur bookings handle karne ki facility deta hai
- **Admins** ko complete system control aur oversight dene ki facility deta hai

---

## 🏗️ System Architecture

### Technology Stack:
- **Backend:** PHP (Server-side scripting)
- **Database:** MySQL (Relational Database Management)
- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript
- **Payment Gateway:** Secure Online Payment System
- **Email Service:** PHPMailer (For OTP, Password Reset, Notifications)
- **Server:** XAMPP (Apache, MySQL, PHP)

### Project Structure:
```
Final_Year_Project/
├── admin/              # Admin Panel
├── manager/            # Manager Panel
├── user/               # User Frontend
├── backend/            # Backend Logic & Config
├── db/                 # Database SQL Files
├── assets/             # Images, CSS, JS
├── uploads/            # User Uploaded Files
└── PHPMailer/          # Email Library
```

---

## 👥 User Roles & Permissions

### 1. **User (Customer)**
- Event browsing aur searching
- Event details dekhna
- Booking create karna
- Add-ons select karna (Food packages, Decoration, Pickup service)
- Payment karna (Secure payment gateway)
- Booking history dekhna
- Tickets download karna
- Feedback submit karna
- Gallery dekhna
- Contact/Support use karna
- Password reset functionality

### 2. **Manager**
- Apne events create, edit, delete karna
- Bookings manage karna (Approve/Reject)
- Add-ons manage karna
- Gallery upload karna
- Payment history dekhna
- Feedback aur ratings dekhna
- Dashboard me statistics dekhna:
  - Live Events count
  - Total Bookings
  - Pending Approvals
  - Revenue (Total & Pending)
  - Average Rating
- Upcoming events monitor karna

### 3. **Admin**
- Complete system overview
- All users manage karna
- All events manage karna
- All bookings manage karna
- Pending approvals handle karna
- Payment details dekhna
- Add-ons manage karna
- Gallery content manage karna
- Feedback center manage karna
- System statistics dekhna:
  - Total Users
  - Total Bookings
  - Pending Approvals
  - Upcoming Events

---

## 🗄️ Database Schema

### Main Tables:

1. **users**
   - User registration aur authentication
   - OTP-based email verification
   - Password reset functionality

2. **events**
   - Event details (title, description, category, date, time, location, price)
   - Event images
   - Status management (planning, active, completed, cancelled, inactive)
   - Manager assignment

3. **bookings**
   - Booking records
   - Booking codes (unique identifiers)
   - Guest count, preferred dates
   - Add-ons selection
   - Total amount calculation
   - Status tracking (pending, approved, confirmed, completed, cancelled)
   - Payment status (unpaid, paid)

4. **addons**
   - Additional services (Food packages, Decoration, Pickup)
   - Pricing information
   - Category-wise organization

5. **payments**
   - Payment transaction records
   - Secure payment processing
   - Transaction IDs
   - Payment status tracking

6. **feedback**
   - Customer reviews aur ratings
   - Booking-based feedback
   - Rating system (1-5 stars)

7. **gallery_item**
   - Event gallery images
   - Manager-uploaded photos

8. **admin** & **manager**
   - Admin aur Manager authentication
   - Role-based access control

---

## 🔑 Key Features

### User Features:

1. **Event Browsing & Search**
   - Category-wise filtering
   - Search by title, description, location
   - Date range filtering
   - Price sorting options
   - Responsive event cards with images

2. **Booking System**
   - Event selection
   - Guest count input
   - Preferred date selection
   - Add-ons selection:
     - Food Packages (Basic, Premium, Signature)
     - Decoration Levels (None, Low, Medium, High)
     - Pickup Service (with address)
   - Real-time price calculation
   - Booking code generation
   - Duplicate booking prevention

3. **Payment Integration**
   - Secure online payment gateway
   - Multiple payment methods (Card, UPI, Net Banking, Wallet)
   - Secure payment processing
   - Payment success/failure handling
   - Real-time payment verification

4. **Ticket Management**
   - Digital ticket generation
   - PDF download functionality
   - Booking code-based ticket access
   - Booking history view

5. **User Account Management**
   - Registration with email verification
   - Login/Logout
   - Password reset via OTP
   - Profile management
   - My Bookings section

6. **Feedback & Support**
   - Event feedback submission
   - Rating system
   - Contact form
   - Support ticket system

### Manager Features:

1. **Event Management**
   - Create new events
   - Edit existing events
   - Event status management
   - Image upload
   - Category assignment

2. **Booking Management**
   - View all bookings for their events
   - Approve/Reject bookings
   - Booking status updates
   - Customer communication

3. **Dashboard Analytics**
   - Live events count
   - Total bookings
   - Pending approvals
   - Revenue tracking (Total & Pending)
   - Average customer rating
   - Upcoming events timeline
   - Recent bookings activity

4. **Add-ons Management**
   - Create/edit add-ons
   - Pricing management
   - Category organization

5. **Gallery Management**
   - Upload event photos
   - Gallery organization
   - Image management

6. **Payment Tracking**
   - Payment history
   - Transaction details
   - Revenue reports

### Admin Features:

1. **System Overview**
   - Complete system statistics
   - User management
   - Event management (all events)
   - Booking management (all bookings)

2. **Approval System**
   - Pending bookings approval
   - Bulk operations
   - Status updates

3. **Content Management**
   - Add-ons management
   - Gallery content management
   - Feedback center management

4. **Reports & Analytics**
   - Payment reports
   - Booking reports
   - User activity reports

---

## 💳 Payment System

### Secure Payment Gateway:
- **Multiple Payment Methods:**
  - Credit/Debit Cards (Visa, Mastercard, Amex)
  - UPI (Google Pay, PhonePe, Paytm)
  - Net Banking (SBI, HDFC, ICICI, Axis, etc.)
  - Wallets (Paytm, Amazon Pay, MobiKwik, Freecharge)
- **Features:**
  - Secure payment processing
  - Real-time transaction verification
  - Transaction history
  - Professional payment UI

### Payment Flow:
1. User booking complete karta hai
2. Payment page par redirect hota hai
3. User payment method select karta hai (Card/UPI/Net Banking/Wallet)
4. Payment details enter karta hai
5. "Pay" button click karta hai
6. Payment process hota hai with secure verification
7. Success page dikhega with transaction details
8. Booking update hoti hai

---

## 🎨 UI/UX Features

1. **Modern Design**
   - Clean aur professional interface
   - Purple color scheme (#5a2ca0 primary color)
   - Responsive design (Mobile, Tablet, Desktop)
   - Bootstrap 5 framework
   - Font Awesome icons

2. **User Experience**
   - Intuitive navigation
   - Clear call-to-action buttons
   - Loading states
   - Error handling with user-friendly messages
   - Success notifications
   - Form validation

3. **Responsive Design**
   - Mobile-first approach
   - Tablet optimization
   - Desktop layouts
   - Touch-friendly interface

---

## 🔐 Security Features

1. **Authentication & Authorization**
   - Session-based authentication
   - Role-based access control
   - Password hashing
   - OTP-based email verification
   - Password reset security

2. **Data Protection**
   - SQL injection prevention (Prepared statements)
   - XSS protection (HTML escaping)
   - CSRF protection
   - Input validation
   - Secure file uploads

3. **Payment Security**
   - Secure payment gateway
   - Real-time payment verification
   - Transaction logging

---

## 📧 Email Functionality

### PHPMailer Integration:
- **OTP Verification:** Registration aur password reset ke liye
- **Password Reset:** Secure password reset links
- **Notifications:** Booking confirmations, status updates

### Email Features:
- SMTP configuration
- HTML email templates
- OTP generation aur verification
- Secure token-based password reset

---

## 🚀 Installation & Setup

### Requirements:
- XAMPP (Apache, MySQL, PHP 7.4+)
- Web browser
- Web browser

### Setup Steps:

1. **Database Setup:**
   - XAMPP start karein
   - phpMyAdmin me jayein
   - New database create karein: `ee`
   - `db/` folder me sabhi SQL files import karein

2. **Configuration:**
   - `backend/config.php` me database credentials update karein

3. **File Permissions:**
   - `uploads/` folder me write permissions enable karein

4. **Access:**
   - User Panel: `http://localhost/Final_Year_Project/user/`
   - Admin Panel: `http://localhost/Final_Year_Project/admin/`
   - Manager Panel: `http://localhost/Final_Year_Project/manager/`

---

## 📱 Pages & Functionality

### User Pages:
- `index.php` - Homepage with slider
- `events.php` - Browse all events
- `event.details.php` - Event details page
- `booking.php` - Booking form
- `peyment.php` - Payment page
- `payment_success.php` - Payment success handler
- `mybooking.php` - Booking history
- `tickets.php` - Ticket management
- `download-ticket.php` - Ticket download
- `gallery.php` - Event gallery
- `feedback.php` - Feedback submission
- `contact.php` - Contact form
- `support.php` - Support system
- `login.php` / `register.php` - Authentication
- `forgot_password.php` - Password reset

### Admin Pages:
- `index.php` - Admin dashboard
- `add_event.php` - Create events
- `manage_events.php` - Manage events
- `pending_approval.php` - Approve bookings
- `payment.php` - Payment details
- `AddOns.php` - Manage add-ons
- `gallery.php` - Gallery management
- `feedback.php` - Feedback center

### Manager Pages:
- `index.php` - Manager dashboard
- `manage_events.php` - Manage own events
- `manage_booking.php` - Manage bookings
- `pending_approval.php` - Approve bookings
- `manage_addOns.php` - Manage add-ons
- `manage_gallary.php` - Gallery uploads
- `manage_payments.php` - Payment history
- `manage_feedback.php` - View feedback

---

## 🔄 Workflow

### Booking Workflow:
1. User event browse karta hai
2. Event details dekh kar booking start karta hai
3. Guest count, date, add-ons select karta hai
4. Booking form submit karta hai
5. Booking code generate hota hai
6. Payment page par redirect hota hai
7. Secure payment gateway se payment complete karta hai
8. Payment verify hota hai
9. Booking status "pending" ho jata hai
10. Manager booking approve/reject karta hai
11. User ko notification milta hai
12. Ticket download kar sakta hai

### Event Management Workflow:
1. Manager/Admin event create karta hai
2. Event details, image, pricing add karta hai
3. Event status "active" set karta hai
4. Event user ko visible ho jata hai
5. Users booking kar sakte hain
6. Manager bookings manage karta hai
7. Event complete hone par status update hota hai

---

## 📊 Reports & Analytics

### Manager Dashboard:
- Live Events Count
- Total Events
- Pending Approvals
- Total Bookings
- Total Revenue (Paid)
- Pending Revenue (Unpaid)
- Average Rating
- Upcoming Events Timeline
- Recent Bookings Activity
- Monthly Revenue Charts

### Admin Dashboard:
- Total Users
- Total Bookings
- Pending Approvals
- Upcoming Events
- Operations Activity Log

---

## 🛠️ Technical Highlights

1. **Code Organization:**
   - Modular structure
   - Separation of concerns
   - Reusable functions
   - Clean code practices

2. **Database Design:**
   - Normalized database structure
   - Foreign key relationships
   - Indexed queries
   - Efficient data retrieval

3. **Error Handling:**
   - Try-catch blocks
   - User-friendly error messages
   - Logging mechanisms
   - Validation at multiple levels

4. **Performance:**
   - Optimized database queries
   - Image optimization
   - Caching strategies
   - Lazy loading

---

## 🎯 Future Enhancements

1. Real-time notifications
2. SMS integration
3. Multi-language support
4. Advanced analytics dashboard
5. Mobile app development
6. Social media integration
7. Event calendar view
8. Email marketing integration
9. Advanced search filters
10. Review system enhancements

---

## 📝 Conclusion

**Event Ease** ek complete, production-ready event booking management system hai jo modern web technologies use karke banaya gaya hai. Ye system users, managers, aur admins ke liye comprehensive features provide karta hai, including event management, booking system, payment processing, aur analytics.

System secure, scalable, aur user-friendly hai, jo real-world event management businesses ke liye perfect solution hai.

---

## 👨‍💻 Developer Notes

- Project XAMPP environment me develop kiya gaya hai
- Database name: `ee`
- Default port: 3306
- PHP version: 7.4+
- Bootstrap version: 5.3.2
- Payment system: Fully integrated with multiple payment methods

---

**Project Name:** Event Ease  
**Type:** Final Year Project  
**Technology:** PHP, MySQL, Bootstrap, Secure Payment Gateway  
**Year:** 2024

