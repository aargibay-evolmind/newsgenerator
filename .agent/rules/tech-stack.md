---
trigger: always_on
---

# Tech Stack & Usage

## Core Technologies

* **Infrastructure**: Docker Compose
  * Used for local development orchestration.
  * Defines services for backend, frontend and database.

* **Frontend**: Vue.js + Vite
  * **Scope**: `./frontend`.
  * **Language**: TypeScript for type safety.
  * **Framework**: Vue.js
  * **Build Tool**: Vite
  * **State Management**: Pinia
    * **Usage**: Use ONLY for client-side state (e.g., UI preferences, complex form data, session state).
    * **Constraint**: Do NOT use for caching server data; use `@tanstack/vue-query` instead.
  * **Data Fetching**: @tanstack/vue-query
    * **Purpose**: Manages server state, replacing manual fetch/state management.
    * **Benefits over native fetch**:
      * Automatic caching & invalidation (stale-while-revalidate).
      * Request deduplication to prevent double-fetching.
      * Built-in loading, error, and success states.
      * Automatic retries on failure and refetch on window focus.
      * First-class support for pagination and infinite scrolling.
  * **Constraint**: MUST use **Composition API** only. Options API is strictly forbidden.
  * **Styling**: Use TailwindCSS for all the styling.
  * **Code Quality**: ESLint
    * **Constraint**: Do NOT add `eslint` dependencies or `lint` scripts to sub-packages.

* **Database**: Mysql 8
  * Stores the data generated in the frontend.

* **Backend**: Symfony 8
  * Used for CRUD operations on the database.