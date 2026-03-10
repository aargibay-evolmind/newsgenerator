---
trigger: always_on
---

# Agent System Rule: Action-Domain-Responder (ADR) Architecture

## Context and Objective
You are an expert PHP/Symfony developer. Your objective is to write, refactor, and review code adhering strictly to the **Action-Domain-Responder (ADR)** architectural pattern. Do not use traditional multi-method Controllers. 

ADR separates the web concern (HTTP) from the application concern (business logic) to ensure strict adherence to the Single Responsibility Principle.

---

## Core Components

When generating or refactoring code, you must divide the logic into these three distinct layers:

### 1. Action (The Entry Point)
* **Role:** The bridge between the HTTP request and the Domain layer.
* **Rules:**
    * One class per route (e.g., `CreateUserAction`, not `UserController`).
    * It only contains a single public method: `__invoke()`.
    * It extracts input from the HTTP Request.
    * It passes that input to the Domain payload/service.
    * It passes the Domain's result to the Responder.
    * **No business logic.**

### 2. Domain (The Core)
* **Role:** Executes the actual business rules, database interactions, and state changes.
* **Rules:**
    * Must be completely agnostic of HTTP (no Request or Response objects here).
    * Includes Services, Repositories, Entities, and Data Transfer Objects (DTOs).
    * Returns a standardized result (e.g., a Domain Payload or DTO) back to the Action.

### 3. Responder (The Presenter)
* **Role:** Transforms the Domain's output into a standardized HTTP Response.
* **Rules:**
    * Takes the Domain output and formats it (e.g., JSON serialization, setting status codes).
    * Handles HTTP-specific logic like headers or cookies.
    * Returns the final `Symfony\Component\HttpFoundation\Response` (or `JsonResponse`).

---

## Implementation Constraints & Formatting

* **Naming Conventions:** Actions must end in `Action` (e.g., `UpdateProductAction`). Responders must end in `Responder` (e.g., `JsonResponder`, `CreatedResponder`).
* **Dependency Injection:** Inject dependencies via the constructor.
* **Fat Domain, Thin Action:** If an Action has more than 10 lines of code, the logic likely belongs in the Domain.

---

## Example Structure Reference

When asked to scaffold a new feature (e.g., creating an Article), use this flow:

1.  **CreateArticleAction**: Injects `CreateArticleService` and `JsonResponder`.
2.  **CreateArticleService (Domain)**: Injects `ArticleRepository`, validates data, saves to DB, returns an `Article` entity.
3.  **JsonResponder**: Takes the `Article` entity, serializes it, and returns a `201 Created` JsonResponse.

---

## Agent Do's and Don'ts

| Do | Don't |
| :--- | :--- |
| Create a single `__invoke()` method per Action. | Create traditional controllers with multiple methods. |
| Use DTOs to pass data between Action and Domain. | Pass the `Request` object into Domain services. |
| Return HTTP Responses exclusively from the Responder. | Return strings or arrays directly from the Action. |
| Handle domain exceptions gracefully in the Responder. | Leak database exceptions to the HTTP layer. |