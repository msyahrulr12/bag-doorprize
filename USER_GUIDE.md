# BAG Doorprize System - User Guide

Welcome to the **BAG Doorprize System**, a comprehensive digital platform designed to manage bank-wide drawing events, participant eligibility, point accumulation, and automated winner selection.

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [User Management & Security](#3-user-management--security)
4. [Master Data Management](#4-master-data-management)
5. [Participant & Customer Management](#5-participant--customer-management)
6. [The Point System](#6-the-point-system)
7. [Lottery Events & Tickets](#7-lottery-events--tickets)
8. [Drawing Winners](#8-drawing-winners)
9. [Maker-Checker Approval Workflow](#9-maker-checker-approval-workflow)
10. [Reporting & Exports](#10-reporting--exports)

---

## 1. Introduction

The BAG Doorprize System automates the process of reward distribution. It integrates with banking systems to import customer points, generate lottery tickets, and conduct fair, weighted drawings.

**Key Features:**

- **Weighted Drawings**: Bias drawings based on geographic regions (JABODETABEK, JABAR JATENG JATIM, SUMATERA, BALI, NTT, MALUKU, SULAWESI, KALIMANTAN, LAINNYA).
- **Maker-Checker System**: Critical actions (like point corrections or deletions) require dual-authorization.
- **Bulk Drawing**: Process hundreds of winners simultaneously with background jobs.
- **Auditability**: Every action is logged and trackable.

---

## 2. Getting Started

### Accessing the Dashboard

1.  Navigate to the application URL.
2.  Login with your credentials.
3.  The **Dashboard** provides an overview of active events, total tickets issued, and recent winners.

---

## 3. User Management & Security

Managed via the **User Management** module.

- **Users**: Create and manage staff accounts.
- **Roles & Permissions**: Assign roles such as `Super Admin`, `Admin`, `Maker`, or `Checker`.
- **Shield**: Advanced permission management to restrict access to specific resources (e.g., only certain users can view the Reports).
- **Audit Logs**: All major user activities are automatically tracked. This includes capturing user logins and exactly when a user starts or stops a public drawing session. You can review these in the **Audits** menu.

---

## 4. Master Data Management

Before starting an event, ensure the following master data is populated:

- **Branches**: Register all participating bank branches, including their Region (crucial for weighted drawings).
- **Products**: Manage banking products that contribute to point accumulation.
- **Prizes**: Create a catalog of prizes, assigning them a **Tier** (Common, Bronze, Silver, Gold, Platinum).

---

## 5. Participant & Customer Management

- **Customers**: The central database of all bank customers.
- **Participants**: Specific entries for customers participating in events.
- **Accounts**: Linking customers to their specific bank account numbers.

---

## 6. The Point System

Points determine the number of lottery tickets a participant receives.

- **Point History**: A view-only record of all point movements.
- **Point Correction**: If a discrepancy occurs, a **Maker** can submit a Point Correction request.
    - _Note: Corrections do not take effect until a **Checker** approves them._

---

## 7. Lottery Events & Tickets

### Managing Events

1.  Go to **Events** and create a new event.
2.  Set start/end dates and event-specific rules.
3.  Assign **Prizes** to the event via the "Event Prizes" section, setting the "Minimum Points Required" for each prize.

### Generating Tickets

The system automatically generates **Lottery Tickets** based on participants' points.

- A ticket represents a range (e.g., A000001 - A000010).
- The "Last Ticket Number" is tracked per event to ensure uniqueness.

---

## 8. Drawing Winners

There are two primary ways to draw winners:

### A. Single Draw

Use the **Draw Winner** page for high-tier prizes:

1.  Select a Prize.
2.  The system calculates region-weighted probability.
3.  Click **Draw** to trigger the animation.
4.  Optionally **Confirm** or **Re-draw** (if configured).

### B. Bulk Draw

Use the **Draw Winner Bulk** page for lower-tier prizes with high volume:

1.  Set the number of winners to pick (Split Draw).
2.  The system runs the drawing in the background.
3.  Monitor progress via the progress bar.
4.  Once complete, review and **Confirm** the batch of winners.

---

## 9. Maker-Checker Approval Workflow

To prevent errors or fraud, critical actions follow the Approval Workflow:

1.  **Submission (Maker)**: A user with "Maker" role performs an action (e.g., Delete a Branch). The system notifies they need approval.
2.  **Review (Checker)**: A user with "Checker" role goes to the **Approvals** menu.
3.  **Action**: The Checker reviews the "Original Data" vs "New Data" and clicks **Approve** or **Reject**.
4.  **Execution**: Upon approval, the system automatically executes the pending action (e.g., the data is deleted or the point correction is applied).

---

## 10. Reporting & Exports

Data can be retrieved in multiple formats:

- **CSV/Excel Exports**: Available on most tables (Users, Winners, Customers). 
- **Data Export Hub**: A specialized menu for exporting massive, gigabyte-sized CSV files directly from the Main database and T24 databases safely using background jobs. These exports automatically group unique accounts and format the data cleanly without duplicating rows.
- **PDF Bank Statements**: Generate personalized statements for customers showing their ticket ranges and points for a specific month.
- **Reporting Dashboard**: Summary widgets and specialized export actions.

---

## 11. System Settings

Staff with administrative access can fine-tune the system's behavior via the **Settings** menu:

- **Point & Eligibility**:
    - `Point Divider`: Determines the ratio for point calculation (e.g., 1 point per 1,000,000 IDR).
    - `Min Opening Balance`: Minimum balance required to start earning points.
    - `Threshold Reduction Balance`: Controls how points are affected by balance drops.
- **Drawing Weights**:
    - `Region Weights`: A JSON configuration that defines the probability of picking a winner from a specific region (e.g., `{"JABODETABEK": 50, "JABAR JATENG JATIM": 15, "SUMATERA": 15, "BALI, NTT, MALUKU": 7, "SULAWESI": 7, "KALIMANTAN": 6, "LAINNYA": 0}`).
- **Drawing Experience**:
    - `Activate Re-draw & Confirm`: If enabled, winners must be manually confirmed or re-drawn. If disabled, winners are recorded immediately after the drawing animation.
    - `Draw Delay`: The duration (in seconds) of the drawing animation.
- **Reporting**:
    - `Merge PDF Bank Statement`: Toggles whether PDF statements are merged or generated individually.
- **System Maintenance & Security**:
    - `application_locked` (Boolean): Set to `1` to instantly lock all regular users out of the system, forcing a `503 Service Unavailable` page. Only users with the `super_admin` or `Admin` roles can bypass this. The Public Draw screens will remain active during a lock.
    - `application_locked_excluded_emails` (String): A comma-separated list of user emails (e.g., `user1@example.com, user2@example.com`) who are allowed to bypass the application lock.
    - `application_locked_excluded_roles` (String): A comma-separated list of roles (e.g., `IT Staff, Manager`) that are allowed to bypass the application lock.

---

## 12. Database Backup & Restore

To protect against data loss during critical operations (like point history processing), the system includes built-in backup and recovery tools.

### Automatic Backups

The system **automatically** performs a database backup whenever the `app:process-point-history-command` is executed. These backups are stored in `storage/app/backups/`.

### Manual Commands

Staff with terminal access can perform manual backups or restorations:

- **Manual Backup**:
    ```bash
    make db-backup
    # or
    php artisan app:database-backup
    ```

- **Database Restore (Rollback)**:
    ```bash
    make db-restore
    # or
    php artisan app:database-restore
    ```
    - The restore command is interactive and will ask you to select a backup file from the list of available ones.
    - **Warning**: Restoring a database will overwrite your current data with the data from the backup file. Use with caution!

---

## Support & Maintenance

For technical issues, please contact the IT System Administrator.

- Ensure the **Queue Worker** is running to process background drawings and imports.
- Check the **Logs** module for troubleshooting specific errors.

---
