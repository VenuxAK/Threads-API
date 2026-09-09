# API Contract: GraphQL & Authentication

> Base URL: `http://localhost:8000` (development)
> Data Protocol: **GraphQL** at `POST /graphql` | Interactive Explorer: `GET /graphiql`
> Authentication Protocol: HTTP Session / Sanctum Cookie at `/auth/*`

```mermaid
flowchart TB
    subgraph Public HTTP
        AUTH_REG[POST /auth/register]
        AUTH_LOGIN[POST /auth/login]
        AUTH_FORGOT[POST /auth/forgot-password]
        GRAPHIQL[GET /graphiql (Dev Explorer)]
        HEALTH[GET /up]
    end
    subgraph GraphQL Unified Endpoint [POST /graphql]
        direction TB
        Q_FEED[query: feed, post, myPosts, userPosts, myLikedPosts]
        Q_USER[query: me, user]
        Q_COMM[query: postComments, commentThread, commentReplies]
        Q_SEARCH[query: search]
        M_POST[mutation: createPost, updatePost, deletePost]
        M_INTER[mutation: likePost, unlikePost, repost]
        M_COMM[mutation: createComment, deleteComment]
        M_PROF[mutation: updateProfile]
    end
    Client[Nuxt 4 Client] --> Public HTTP
    Client -->|Session Cookie + CSRF| GraphQL Unified Endpoint
```

---

## 1. Conventions

### 1.1 Authentication & Session Lifecycle
- **Session Bootstrap**: Handled via standard RESTful endpoints (`/auth/login`, `/auth/register`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`).
- **CSRF Protection**: The client fetches `/sanctum/csrf-cookie` prior to login. Subsequent requests pass `X-XSRF-TOKEN` and `credentials: 'include'`.
- **GraphQL Authorization**: Handled via Lighthouse's `@guard` directive on each protected query and mutation. If unauthenticated, Lighthouse returns a standard GraphQL error with code `401 / Unauthenticated`.

### 1.2 GraphQL Request & Response Envelope

#### Request (`POST /graphql`):
```json
{
  "query": "query GetFeed($page: Int) { feed(page: $page) { data { id content } } }",
  "variables": { "page": 1 }
}
```

#### Success Response:
```json
{
  "data": {
    "feed": {
      "data": [
        { "id": "6aa152b2c4182a742a049f12", "content": "Hello Threads!" }
      ],
      "pagination": {
        "total": 42,
        "per_page": 15,
        "current_page": 1,
        "last_page": 3,
        "has_more": true
      }
    }
  }
}
```

#### Error Response:
```json
{
  "errors": [
    {
      "message": "Unauthenticated.",
      "locations": [{ "line": 1, "column": 3 }],
      "path": ["feed"],
      "extensions": {
        "category": "authentication"
      }
    }
  ]
}
```

---

## 2. Authentication Endpoints (`/auth`)

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/auth/register` | Register new user account | No (Guest) |
| `POST` | `/auth/login` | Authenticate and set session cookie | No (Guest) |
| `POST` | `/auth/logout` | Invalidate active session cookie | Yes |
| `POST` | `/auth/forgot-password` | Send password reset link | No |
| `POST` | `/auth/reset-password` | Reset password using token | No |
| `GET` | `/auth/verify-email/{id}/{hash}` | Verify email address | Yes (Signed URL) |
| `POST` | `/auth/email/verification-notification` | Resend verification email | Yes |

---

## 3. GraphQL Operations (`POST /graphql`)

### 3.1 Queries

| Operation | Arguments | Return Type | Description |
| :--- | :--- | :--- | :--- |
| `me` | None | `User` | Current authenticated user profile |
| `user` | `username: String!` | `User` | Public user profile by handle |
| `feed` | `page: Int, perPage: Int` | `PostPaginator!` | Paginated global posts feed |
| `post` | `id: ID!` | `Post` | Retrieve single post by ID |
| `myPosts` | `page: Int, perPage: Int` | `PostPaginator!` | Posts authored by active user |
| `myReposts` | `page: Int, perPage: Int` | `PostPaginator!` | Posts reposted by active user |
| `userPosts` | `username: String!, page: Int, perPage: Int` | `PostPaginator!` | Posts authored by a specific user |
| `userReposts`| `username: String!, page: Int, perPage: Int` | `PostPaginator!` | Posts reposted by a specific user |
| `myLikedPosts` | `page: Int, perPage: Int` | `PostPaginator!` | Posts liked by the active user |
| `postComments` | `postId: ID!` | `[Comment!]!` | Top-level comments for a post |
| `commentThread`| `id: ID!` | `[Comment!]!` | Flat chronological tree via `$graphLookup` |
| `commentReplies`| `id: ID!` | `[Comment!]!` | Direct replies to a comment |
| `search` | `keyword: String!, includePosts: Boolean, page: Int, perPage: Int` | `SearchResult!` | Search users and MongoDB posts |

### 3.2 Mutations

| Operation | Arguments | Return Type | Description |
| :--- | :--- | :--- | :--- |
| `createPost` | `content: String!` | `Post!` | Create and publish thread with hashtag extraction |
| `updatePost` | `id: ID!, content: String!` | `Post!` | Update post content (owner only) |
| `deletePost` | `id: ID!` | `Boolean!` | Delete post and MySQL metadata (owner only) |
| `likePost` | `postId: ID!` | `InteractionResult!` | Toggle like on a post |
| `unlikePost` | `postId: ID!` | `InteractionResult!` | Explicitly unlike a post |
| `repost` | `postId: ID!` | `InteractionResult!` | Toggle repost on a post |
| `createComment`| `postId: ID!, content: String!, parentId: ID` | `Comment!` | Create root comment or nested reply |
| `deleteComment`| `id: ID!` | `Boolean!` | Delete comment (owner only) |
| `updateProfile`| `name: String, bio: String, avatar: String` | `User!` | Update profile details for active user |

---

## 4. Security Controls

1. **Query Depth Limiting**: Lighthouse strictly limits query nesting to a maximum depth of **8** (configured in `config/lighthouse.php`).
2. **Query Complexity Limiting**: Complexity scores cannot exceed **200** per operation.
3. **Web Application Firewall (WAF)**:
   - Rate limiting on `POST /graphql` enforced at **120 requests/minute**.
   - Input payloads and variables are scanned for injection patterns.
   - Raw query AST syntax is exempted from false-positive keyword matching.
