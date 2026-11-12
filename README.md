# Installation and User Guide for Mail Marketing Events System

Welcome to the official guide for the Mail Marketing Events System. This system is designed to help you easily manage guest RSVPs and send marketing emails (such as Save the Date, Formal Invitations, etc.) for weddings or other special events.

---

## 1. Installation Guide

This system is web-based (PHP) and requires a web server with a MySQL/MariaDB database. If you are using cPanel/Hosting, follow these steps:

### A. File and Database Preparation
1. **Upload Files**: Upload all system files (including the `lib` folder, `setup.php`, `dashboard.php`, etc.) into the `public_html` folder or your domain/subdomain folder.
2. **Create Database**: Create a new MySQL database, along with a user and password, through the *MySQL Databases* menu in your cPanel.

### B. Database Configuration
1. Open the `database.php` file using File Manager or a text editor.
2. Change the following configuration according to the database you have created:
   ```php
   $host = 'localhost';
   $db   = 'your_database_name';
   $user = 'your_database_user';
   $pass = 'your_database_password';
   ```
3. Save the changes to the `database.php` file.

### C. Running Automated Setup
1. Open your browser and access the setup page: `https://yourdomain.com/setup.php` (adjust to the URL where you installed it).
2. The system will automatically create the necessary database tables and set up the first admin.
3. If a success message appears ("Setup Database Selesai!"), click the link to go to the Login Page.

---

## 2. Basic User Guide

After the installation is complete, you can start using the system through the Dashboard.

### A. Creating the Admin Account & Login
1. After running setup, open `https://yourdomain.com/create_admin.php`.
2. This secure page will allow you to create your first Super Admin account. Enter your preferred Username, Email, Business Name, and Password. (Note: For security reasons, this page will automatically lock itself once the first account is created).
3. After successfully creating the account, go to `https://yourdomain.com/login.php` to sign in.
4. Once logged in, you will be directed to the Dashboard page.

### B. Brand Settings (White-label & General Settings)
Before adding guests and sending emails, it is highly recommended to set up your event profile:
1. Click the **Settings** menu (gear icon).
2. Fill in profile details such as Event/Couple Name, Event Date & Time, Location, and Google Maps URL.
3. You can configure the **Email Provider**. We highly recommend using **SendGrid** for better delivery rates (Inbox), or you can use your own hosting's **Custom SMTP**.
4. Click **Save Settings** at the bottom.

### C. Managing Guests
1. Go to the **Guests** menu.
2. Click the **Add Guest** button to add guests manually, or use the **Import** button to insert multiple guests at once from a CSV file.
3. On this page, you can also see the RSVP status of each guest (Attending, Pending, or Declined).

### D. Configuring RSVP Questions
1. Open the **RSVP Form** menu.
2. Check the boxes for the questions you want to ask guests when they fill out the RSVP (e.g., Meal Preference, Events Attended, or Additional Notes).
3. You can share the RSVP link with guests. Each guest's unique link can be copied from the **Guests** page.

### E. Sending Email Campaigns
The system allows you to send various types of emails (Save the Date, Invitation, Reminder, etc.).
1. Open the **Email Campaigns** menu.
2. Click **Create Campaign**.
3. Select the type of email you want to send. The built-in HTML editor will adapt the appearance to your profile.
4. Determine who the email will be sent to (e.g., All guests, or guests who have not responded to the RSVP).
5. Click **Send Now** or you can schedule it.

### F. Scanner / Check-in Feature
On the day of the event, your receptionist can use the Check-in feature:
1. Create a receptionist account from the **Settings** page under **Receptionist Accounts**.
2. The receptionist logs in at `https://yourdomain.com/receptionist.php` using their username and PIN.
3. The receptionist can scan guest QR codes or manually search for guest names to perform Check-in.

---

## Frequently Asked Questions (FAQ)

**Q: Why are my emails going to the Spam folder?**
A: Make sure you are using a credible Email Provider (like SendGrid) and that the Sender Email is properly verified. Also, ensure that DNS settings (SPF, DKIM, DMARC) in your hosting are configured.

**Q: How do I delete a guest account?**
A: Open the **Guests** menu, find the guest you want to delete, then click the trash can icon (Delete) on the right side of the guest's name row.

**Q: Can I change the email colors?**
A: Yes, you can change the Primary and Secondary colors through the **Settings** menu, which will change the overall color theme on emails and the RSVP form.

---
Thank you for using the Mail Marketing Events System! We hope your event runs smoothly.
