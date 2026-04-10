# Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : "has"
    PROPERTY_TYPES ||--o{ PROPERTIES : "has"
    PROPERTIES ||--o{ BOOKINGS : "has"

    USERS {
        id PK
        name
        email
        password
        role
        created_at
        updated_at
    }
    PROPERTY_TYPES {
        id PK
        name
        description
        created_at
        updated_at
    }
    PROPERTIES {
        id PK
        property_type_id FK
        name
        description
        capacity
        status
        created_at
        updated_at
    }
    BOOKINGS {
        id PK
        booking_code UK
        property_id FK
        user_id FK
        contact_name
        contact_email
        contact_phone
        institution
        start_date
        end_date
        status
        created_at
        updated_at
    }
```

**Relationships:**
- property_types 1 → many properties
- properties 1 → many bookings
- users 1 → many bookings

**Status Values:**
- Room: available, occupied, used, maintenance
- Booking: scheduled, in_use, finished, cancelled
