# CampusMart — ER Diagram

```mermaid
erDiagram
    users ||--o{ listings : "sells"
    users ||--o{ conversations : "buys (buyer_id)"
    users ||--o{ conversations : "sells (seller_id)"
    users ||--o{ messages : "sends"
    users ||--o{ wishlists : "saves"
    users ||--o{ reviews : "writes (reviewer)"
    users ||--o{ reviews : "receives (seller)"
    users ||--o{ reports : "files"
    users ||--o{ lost_found : "posts"
    users ||--o{ notifications : "receives"

    categories ||--o{ listings : "groups"

    listings ||--o{ listing_images : "has"
    listings ||--o{ conversations : "discussed in"
    listings ||--o{ wishlists : "saved in"
    listings ||--o{ reviews : "reviewed via"
    listings ||--o{ reports : "reported in"

    conversations ||--o{ messages : "contains"

    users {
        int id PK
        string name
        string email UK
        string password_hash
        enum role "student|admin"
        bool is_verified
        bool is_banned
        string verify_token
        string reset_token
        datetime reset_expires
        datetime created_at
    }
    categories {
        int id PK
        string name
        string slug UK
        string color
    }
    listings {
        int id PK
        int user_id FK
        int category_id FK
        string title
        text description
        decimal price
        enum condition "new|like_new|good|fair"
        string campus
        string location
        enum status "active|sold|deleted"
        int views
    }
    listing_images {
        int id PK
        int listing_id FK
        string filename
        int sort_order
    }
    conversations {
        int id PK
        int listing_id FK
        int buyer_id FK
        int seller_id FK
        datetime last_message_at
        string UK "listing_id + buyer_id"
    }
    messages {
        int id PK
        int conversation_id FK
        int sender_id FK
        text body
        bool is_read
    }
    wishlists {
        int id PK
        int user_id FK
        int listing_id FK
        string UK "user_id + listing_id"
    }
    reviews {
        int id PK
        int listing_id FK
        int reviewer_id FK
        int seller_id FK
        int rating "1..5"
        text comment
        string UK "listing_id + reviewer_id"
    }
    reports {
        int id PK
        int reporter_id FK
        int listing_id FK "nullable"
        int reported_user_id FK "nullable"
        string reason
        text details
        enum status "pending|reviewed|dismissed"
    }
    lost_found {
        int id PK
        int user_id FK
        enum type "lost|found"
        string title
        text description
        string image "nullable"
        string location
        enum status "open|resolved"
    }
    notifications {
        int id PK
        int user_id FK
        string type
        string title
        text body
        string link
        bool is_read
    }
    settings {
        string setting_key PK
        string setting_value
    }
    allowed_domains {
        int id PK
        string domain UK
    }
    login_attempts {
        int id PK
        string ip
        string email
        datetime attempted_at
    }
```
