# ThreadsApp API

A high-performance, secure social media API built with Laravel 12, featuring a hybrid database architecture (MySQL + MongoDB) and comprehensive Web Application Firewall (WAF) protection.

## 🚀 Features

### **Core Functionality**
- **User Management**: Registration, authentication, profiles with Laravel Sanctum
- **Post System**: Create, read, update, delete posts with hashtag extraction
- **Social Interactions**: Like, share, and view post interactions
- **User Profiles**: View own profile and other users' profiles with posts
- **Search**: Search users and posts with advanced filtering
- **Hybrid Database**: MySQL for relational data + MongoDB for scalable post storage

### **Security & Performance**
- **Web Application Firewall (WAF)**: Comprehensive security middleware
- **Rate Limiting**: Configurable limits for API endpoints
- **Security Headers**: HSTS, CSP, X-Frame-Options, and more
- **File Upload Protection**: Validation and sanitization
- **RoadRunner**: High-performance PHP application server
- **MongoDB Integration**: Scalable NoSQL storage for posts

## 📋 API Endpoints

### **Authentication** (`/api/v1/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/login` | User login | No |
| POST | `/auth/register` | User registration | No |
| POST | `/auth/logout` | User logout | Yes |
| POST | `/auth/forgot-password` | Request password reset | No |
| POST | `/auth/reset-password` | Reset password | No |

### **User Management** (`/api/v1/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/me/profile` | Get authenticated user profile | Yes |
| GET | `/users/{username}` | Get user profile | Yes |
| GET | `/users/{username}?posts=include` | Get user profile with posts | Yes |
| GET | `/users/{username}?post={post_id}` | Get user's specific post | Yes |

### **Posts** (`/api/v1/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/posts` | Get all posts (public) | No |
| GET | `/posts/{id}` | Get specific post | No |
| GET | `/me/posts` | Get authenticated user's posts | Yes |
| POST | `/me/posts` | Create new post | Yes |
| PUT | `/me/posts/{id}` | Update post | Yes |
| DELETE | `/me/posts/{id}` | Delete post | Yes |

### **Post Interactions** (`/api/v1/posts/{id}/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/like` | Like a post | Yes |
| DELETE | `/like` | Unlike a post | Yes |
| POST | `/share` | Share a post | Yes |
| GET | `/interactions` | Get post interaction counts | Yes |

### **Search** (`/api/v1/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/search` | Search users (and posts with `?posts=include`) | Yes |

### **Utility** (`/api/v1/`)
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/waf-test` | Test WAF functionality | No |
| GET | `/ping-mongodb` | Test MongoDB connection | No |

## 🛠️ Technology Stack

- **Backend Framework**: Laravel 12
- **Application Server**: RoadRunner
- **Authentication**: Laravel Sanctum
- **Primary Database**: MySQL
- **Document Storage**: MongoDB
- **API Security**: Custom WAF Middleware
- **Testing**: PHPUnit with comprehensive test suite
- **Performance**: Redis (caching), Queue workers

## 🚦 Quick Start

### **Prerequisites**
- PHP 8.3+
- Composer
- MySQL 8.0+
- MongoDB 6.0+
- Redis 7.0+
- RoadRunner

### **Installation**

1. **Clone the repository**
   ```bash
   git clone https://github.com/VenuxAK/Threads-API.git
   cd Threads-API
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update `.env` file**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=threadsapp
   DB_USERNAME=root
   DB_PASSWORD=
   
   MONGODB_URI=mongodb://localhost:27017
   MONGODB_DATABASE=threadsapp
   
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   
   # WAF Configuration
   WAF_ENABLED=true
   WAF_MODE=protect
   WAF_BYPASS_TOKENS=
   WAF_IP_WHITELIST=127.0.0.1,::1
   ```

5. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Start the server**
   ```bash
   # Using RoadRunner
   ./rr serve
   
   # Or using Laravel's built-in server
   php artisan serve
   ```

## 🔧 Configuration

### **WAF Configuration**
The Web Application Firewall can be configured in `config/waf.php`:

```php
return [
    'enabled' => env('WAF_ENABLED', true),
    'mode' => env('WAF_MODE', 'protect'), // 'monitor' or 'protect'
    'bypass_tokens' => explode(',', env('WAF_BYPASS_TOKENS', '')),
    
    // Rate limiting
    'rate_limits' => [
        'api' => env('WAF_RATE_LIMIT_API', 100),
        'auth' => env('WAF_RATE_LIMIT_AUTH', 10),
        'register' => env('WAF_RATE_LIMIT_REGISTER', 3),
    ],
    
    // Security patterns
    'patterns' => [
        'sql_injection' => [
            '/union.*select/i',
            '/drop.*table/i',
            '/or\s+1=1/i',
            // ... more patterns
        ],
        'xss' => [
            '/<script/i',
            '/javascript:/i',
            '/onload=/i',
            // ... more patterns
        ],
    ],
];
```

### **Database Configuration**
The project uses a hybrid database approach:
- **MySQL**: Users, authentication, post metadata
- **MongoDB**: Post content, tags, and scalable document storage

## 🧪 Testing

The project includes comprehensive test coverage:

### **Run all tests**
```bash
php artisan test
```

### **Run specific test suites**
```bash
# Authentication tests
php artisan test --filter=AuthTest

# Post functionality tests
php artisan test --filter=PostTest

# User profile tests
php artisan test --filter=UserProfileTest

# Post interaction tests
php artisan test --filter=PostInteractionTest

# MongoDB integration tests
php artisan test --filter=MongoDBTest

# WAF security tests
php artisan test --filter=WafTest
```

### **Test Coverage**
- **Authentication**: 100% coverage
- **Post CRUD**: 100% coverage
- **User Profiles**: 100% coverage
- **Post Interactions**: 100% coverage
- **MongoDB Operations**: 100% coverage
- **WAF Security**: 100% coverage

## 🛡️ Security Features

### **Web Application Firewall**
- SQL injection prevention
- XSS attack blocking
- Rate limiting per endpoint
- IP whitelisting/blacklisting
- File upload validation
- Request size limits

> **Detailed WAF Documentation**: See [WAF_README.md](WAF_README.md) for comprehensive configuration and [WAF_IMPLEMENTATION.md](WAF_IMPLEMENTATION.md) for implementation details.

### **Security Headers**
- HTTP Strict Transport Security (HSTS)
- Content Security Policy (CSP)
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy

### **Authentication & Authorization**
- Laravel Sanctum tokens
- Password hashing with bcrypt
- Email verification
- Password reset functionality
- Session management

## 📊 Database Schema

### **MySQL Tables**
```sql
-- Users table
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    username VARCHAR(255) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    avatar TEXT,
    bio TEXT,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Post metadata table
CREATE TABLE post_meta_data (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id VARCHAR(255),
    user_id BIGINT,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### **MongoDB Collections**
```javascript
// Posts collection
{
  "_id": ObjectId,
  "content": String,
  "tags": Array,
  "user_id": Number,
  "created_at": ISODate,
  "updated_at": ISODate
}
```

## 🔄 API Response Format

All API responses follow a standardized format:

### **Success Response**
```json
{
  "success": true,
  "data": {
    // Response data
  }
}
```

### **Error Response**
```json
{
  "success": false,
  "message": "Error description",
  "code": 400
}
```

### **Paginated Response**
```json
{
  "success": true,
  "data": {
    "posts": {
      "data": [
        // Array of posts
      ],
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  }
}
```

## 🚀 Deployment

### **Using Docker**
```bash
docker-compose up -d
```

### **Using RoadRunner in Production**
```bash
# Build RoadRunner binary
./rr build

# Start in production mode
./rr serve -c .rr.prod.yaml
```

### **Environment Variables for Production**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=production-db-host
DB_PORT=3306
DB_DATABASE=threadsapp_prod
DB_USERNAME=prod_user
DB_PASSWORD=strong_password

# MongoDB
MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net

# Redis
REDIS_HOST=redis-host
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

# Security
WAF_ENABLED=true
WAF_MODE=protect
WAF_IP_WHITELIST=your_server_ip
```

## 📈 Performance Optimization

### **Caching Strategy**
- Redis for session storage
- Database query caching
- API response caching
- Rate limiting storage

### **Database Optimization**
- MongoDB indexes on frequently queried fields
- MySQL indexes on foreign keys
- Query optimization with Laravel Scout
- Connection pooling

### **Application Optimization**
- RoadRunner for persistent processes
- OpCache for PHP bytecode caching
- Gzip compression for responses
- CDN for static assets

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### **Development Guidelines**
- Write tests for new functionality
- Follow PSR-12 coding standards
- Update documentation for API changes
- Use meaningful commit messages
- Ensure all tests pass before submitting PR

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: Check the [Wiki](https://github.com/VenuxAK/Threads-API/wiki)
- **Issues**: [GitHub Issues](https://github.com/VenuxAK/Threads-API/issues)
- **Discussions**: [GitHub Discussions](https://github.com/VenuxAK/Threads-API/discussions)

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP framework
- [MongoDB](https://www.mongodb.com) - NoSQL database
- [RoadRunner](https://roadrunner.dev) - PHP application server
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - API authentication

---

**ThreadsApp API** - A modern, secure, and scalable social media API built with cutting-edge technologies.