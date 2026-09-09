# GraphQL Developer Guide & Learning Handbook

Welcome to the GraphQL guide for **ThreadsApp**! This document is designed as a learning companion and technical reference for developers working with GraphQL in this codebase.

---

## 1. What is GraphQL?

**GraphQL** is an open-source query language for APIs and a runtime for fulfilling those queries with your existing data. It was created by Meta (Facebook) in 2012 and open-sourced in 2015.

### REST vs. GraphQL Comparison

| Feature | REST API | GraphQL |
| :--- | :--- | :--- |
| **Endpoint Structure** | Multiple endpoints (`/posts`, `/posts/1/comments`, `/users/me`) | Single smart endpoint (`POST /graphql`) |
| **Data Fetching** | Fixed server response shape | Client specifies exact fields needed |
| **Over-fetching** | Server returns unused fields (e.g., fetching whole user when only needing avatar) | Client only asks for `avatar`, nothing else returned |
| **Under-fetching (N+1)** | Making 3+ round-trips to get a post, its author, and comments | Single round-trip retrieves post, author, and comments together |
| **Type System** | Loose (requires external OpenAPI / Swagger docs) | Strictly typed Schema Definition Language (SDL) |

---

## 2. Core Concepts & Building Blocks

### 2.1 The Schema (SDL)
In GraphQL, the schema is the contract between client and server. It defines every type, field, query, and mutation available.

```graphql
type User {
  id: ID!
  name: String!
  username: String!
  avatar: String
}
```
- `ID!`, `String!`: The exclamation mark (`!`) means **non-null** (the field will never return `null`).
- Without `!`, the field is **nullable** (can be `null`, e.g. optional `avatar`).

### 2.2 Operations: Queries vs. Mutations

```
┌──────────────────────────────────────────────────────────────┐
│                    GraphQL Operations                        │
├──────────────────────────────┬───────────────────────────────┤
│            Query             │           Mutation            │
│       (Read Operations)      │       (Write Operations)      │
│  - Get Home Feed             │  - Publish a New Post         │
│  - Search Users & Posts      │  - Like / Unlike a Post       │
│  - View User Profile         │  - Create a Nested Comment    │
└──────────────────────────────┴───────────────────────────────┘
```

#### Query Syntax:
```graphql
query GetFeed($page: Int, $perPage: Int) {
  feed(page: $page, perPage: $perPage) {
    data {
      id
      content
      likes
      author {
        username
      }
    }
    pagination {
      total
      has_more
    }
  }
}
```

#### Mutation Syntax:
```graphql
mutation CreateNewPost($content: String!) {
  createPost(content: $content) {
    id
    content
    published_at
    author {
      username
    }
  }
}
```

### 2.3 Fragments
Fragments are reusable chunks of field selections to keep queries DRY (Don't Repeat Yourself):

```graphql
fragment PostFields on Post {
  id
  content
  likes
  author {
    username
    avatar
  }
}

query GetFeed {
  feed(page: 1) {
    data {
      ...PostFields
    }
  }
}
```

---

## 3. How GraphQL Works in ThreadsApp

### 3.1 Backend: Laravel 12 + Nuwave Lighthouse
We use **Nuwave Lighthouse**, the premier GraphQL framework for Laravel:
- **Schema File**: Located at [`api/graphql/schema.graphql`](file:///home/venux/Desktop/Projects/Threads/api/graphql/schema.graphql).
- **Directives**:
  - `@guard`: Enforces Sanctum session authentication. If the user is unauthenticated, Lighthouse returns a `401 Unauthorized` GraphQL error.
  - `@field(resolver: "App\\GraphQL\\Queries\\FeedQuery")`: Directs execution of the field to a dedicated PHP resolver class.

### 3.2 The Hybrid Storage Challenge: MongoDB + MySQL
A unique architectural feature of ThreadsApp is our **hybrid database**:
- **MongoDB** stores feed content: `posts`, `comments`.
- **MySQL** stores relational data: `users`, `post_meta_data`, `post_likes`, `post_reposts`.

#### The Problem: Naive GraphQL causes $N+1$ queries
If a query asks for 15 posts and their authors:
- 1 query to MongoDB for 15 posts.
- If we naively resolved each author individually: 15 separate queries to MySQL (`SELECT * FROM users WHERE id = ?`).
- That is $1 + 15 = 16$ database queries for a single feed request!

#### The Solution: Batch Loading
Our resolver classes (such as [`FeedQuery`](file:///home/venux/Desktop/Projects/Threads/api/app/GraphQL/Queries/FeedQuery.php) and [`PostTransformer`](file:///home/venux/Desktop/Projects/Threads/api/app/Transformers/PostTransformer.php)) resolve relationships in **bulk**:
1. Fetch 15 posts from MongoDB in 1 query.
2. Extract all unique `user_id`s: `[1, 5, 8]`.
3. Fetch all authors from MySQL in **one** query: `User::whereIn('id', [1, 5, 8])` (cached for 5 minutes).
4. Fetch all metadata counters in **one** query: `PostMetaData::whereIn('post_id', [...])`.
5. Combine them in memory.
**Total Queries: 3**, regardless of whether you fetch 10 or 50 posts!

---

## 4. How to Test GraphQL Using GraphiQL

The backend includes the **GraphiQL Interactive IDE**:
1. Start the backend: `php artisan serve` (or via RoadRunner).
2. Open your browser to: **`http://localhost:8000/graphiql`**.
3. You can explore the documentation schema in the sidebar and run live queries.

> [!TIP]
> Because queries are protected by `@guard`, make sure you have an active session cookie or pass an `Authorization: Bearer <token>` header in the "HTTP Headers" pane at the bottom left of GraphiQL:
> ```json
> {
>   "Authorization": "Bearer 1|your-sanctum-token-here"
> }
> ```

---

## 5. Operations Cookbook (Ready-to-Use Examples)

### 5.1 Fetch Current User Profile
```graphql
query GetMyProfile {
  me {
    id
    name
    username
    email
    avatar
    bio
    created_at
  }
}
```

### 5.2 Fetch Paginated Feed
```graphql
query GetFeed {
  feed(page: 1, perPage: 10) {
    data {
      id
      content
      tags
      published_at
      likes
      comments
      reposts
      is_liked
      is_reposted
      author {
        id
        username
        avatar
      }
    }
    pagination {
      total
      current_page
      last_page
      has_more
    }
  }
}
```

### 5.3 Publish a New Thread
```graphql
mutation PublishThread {
  createPost(content: "Learning GraphQL with Nuwave Lighthouse and Nuxt 4! #graphql #dev") {
    id
    content
    tags
    published_at
    author {
      username
    }
  }
}
```

### 5.4 Like / Unlike a Thread
```graphql
mutation ToggleLike {
  likePost(postId: "6aa152b2c4182a742a049f12") {
    count
    status
  }
}
```

### 5.5 Fetch Recursive Comment Thread
```graphql
query GetThread {
  commentThread(id: "6aa152e4aaea9da68906d282") {
    id
    content
    post_id
    parent_id
    created_at
    replying_to {
      username
    }
    author {
      username
      avatar
    }
  }
}
```

---

## 6. How the Frontend Uses GraphQL

In [`frontend/composables/useGraphQL.ts`](file:///home/venux/Desktop/Projects/Threads/frontend/composables/useGraphQL.ts), we use a lightweight wrapper around `useSanctumClient()`:

```typescript
const { query, mutate } = useGraphQL();

// Executing a Query
const { data, error } = await query<{ feed: { data: Post[] } }>(FEED_QUERY, {
  page: 1,
  perPage: 15,
});

// Executing a Mutation
const { data, error } = await mutate<{ likePost: { count: number; status: boolean } }>(
  LIKE_POST_MUTATION,
  { postId: '123' },
);
```

### Why not Apollo Client?
In Nuxt 4, Apollo Client introduces heavy client-side bundle size, SSR hydration complexity, and conflicting caching layers with Pinia. Our `useGraphQL` composable:
- Shares the browser's cookie session automatically (`credentials: 'include'`).
- Sends CSRF tokens (`X-XSRF-TOKEN`) transparently.
- Keeps Pinia (`stores/posts.ts` and `stores/interactions.ts`) as the single reactive source of truth.
