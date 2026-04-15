# Software Requirements Specification (SRS)

## 1. Introduction

### 1.1 Purpose
The Bedroom and Meeting Room Management System is designed to manage room reservations and availability for two types of rooms: Bedrooms and Meeting Rooms. The system is accessible only by Admin users via a Flutter mobile application, interfacing with a Laravel backend and PostgreSQL database.

### 1.2 Scope
The system enables Admins to manage property types, rooms, bookings, and monitor room availability. All operations are performed through a secure, authenticated interface.

### 1.3 Definitions
- **Admin**: Authorized user responsible for managing all system operations.
- **Property Type**: Category of room (Bedroom, Meeting Room).
- **Room**: Individual space available for booking.
- **Booking**: Scheduled reservation of a room.

## 2. Overall Description

### 2.1 System Architecture
- **Frontend**: Flutter Mobile Application
- **Backend**: Laravel RESTful API
- **Database**: PostgreSQL

#### Communication Flow
Flutter Mobile App → REST API (Laravel) → Business Logic → PostgreSQL Database

### 2.2 System Users
- **Admin**: Only user type. Responsible for all management operations.

## 3. Functional Requirements

### 3.1 Admin Login
- Login with email and password
- Access dashboard upon successful authentication

### 3.2 Property Type Management
- Create, view, update, delete property types (Bedroom, Meeting Room)
- Property Type attributes: name, description, is_continuous_booking (boolean flag indicating whether the booking logic requires a continuous schedule or permits disjoint slot schedules)

### 3.3 Room Management
- Add, view, update, delete rooms
- Room attributes: name, property type, capacity, description, status (available, occupied, used, maintenance)

### 3.4 Booking Management
- Create, view, edit, cancel bookings
- Booking attributes: booking code, room, contact name, contact email, contact phone, institution, status (scheduled, in_use, finished, cancelled)
- Booking schedule attributes: specific time slot arrays (e.g., specific shifts on different days for Meeting Room, or single check-in/out range for Bedroom).

### 3.5 Room Availability Monitoring
- Automatic status update based on booking schedule

## 4. Non-Functional Requirements

### 4.1 Security
- Authentication required for all operations
- Only Admin users permitted

### 4.2 Usability
- Simple, intuitive interface for administrators

### 4.3 Performance
- Fast loading of room and booking data

### 4.4 Data Storage
- All data stored in PostgreSQL

## 5. Database Design

### 5.1 Tables
- **users**: Admin accounts
- **property_types**: Room categories
- **properties**: Room information
- **bookings**: Booking metadata (contact, status, unique code)
- **booking_schedules**: Detailed booking time slots (supports disjoint meeting schedules or continuous bedroom stays)

### 5.2 Relationships
- property_types 1 → many properties
- properties 1 → many bookings
- users 1 → many bookings
- bookings 1 → many booking_schedules

## 6. Example Data

### 6.1 Property Types
- Bedroom
- Meeting Room

### 6.2 Rooms
- Bedroom 101
- Bedroom 102
- Meeting Room A

### 6.3 Bookings
- Meeting Room A booked by Budi from 10:00 to 12:00

---

## 7. Diagrams

See diagrams in the following files:
- System Architecture Diagram: architecture_diagram.md
- Entity Relationship Diagram (ERD): erd_diagram.md
