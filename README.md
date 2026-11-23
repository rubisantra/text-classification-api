# AI Assignments: Text Classification API & Email Template Generator

Both assignments are implemented in a single **Laravel (PHP 7.4)** project and use the **Groq API**
(OpenAI-compatible, model `llama-3.3-70b-versatile`) as the AI provider.

| # | Assignment | Endpoint |
|---|------------|----------|
| 1 | AI-Assisted Text Classification API | `POST /api/classify` |
| 2 | AI-Powered Email Template Generator Service | `POST /api/generate-email` |

---

## Tech Stack
- PHP 7.4, Laravel
- Groq Chat Completions API (`/openai/v1/chat/completions`)
- Laravel HTTP client for the AI call
- Postman collection included

## Project Structure
```
app/
  Http/
    Controllers/
      ClassificationController.php     # Assignment 1 (thin controller)
      EmailTemplateController.php      # Assignment 2 (thin controller)
    Requests/
      ClassifyTextRequest.php          # Assignment 1 validation
      GenerateEmailRequest.php         # Assignment 2 validation
  Services/
    TextClassificationService.php      # Assignment 1: prompt, AI call, parsing
    EmailTemplateService.php           # Assignment 2: prompt, AI call, parsing, timing log
config/
  ai.php                               # AI settings read from environment variables
  logging.php                          # contains the dedicated `ai` log channel
routes/
  api.php                              # routing
postman/
  laravel-assignment.postman_collection.json
```
Controllers only handle the HTTP request/response and error handling. All AI logic
lives in the service classes.

---

## Environment Setup

### Prerequisites
- PHP 7.4+ and Composer
- A free Groq API key from https://console.groq.com/keys

### Steps
```bash
# 1. Install dependencies
composer install

# 2. Create the environment file
copy .env.example .env        # Windows
# cp .env.example .env        # macOS / Linux

# 3. Generate the app key
php artisan key:generate

# 4. Add your Groq API key to .env (see below)

# 5. Clear cached config and start the server
php artisan config:clear
php artisan serve
```
The API runs at `http://127.0.0.1:8000`.

### Environment variables
```env
AI_API_KEY=your_groq_api_key_here
AI_BASE_URL=https://api.groq.com/openai/v1
AI_MODEL=llama-3.3-70b-versatile
AI_TIMEOUT=30
```
- The API key is read **only** from environment variables via `config/ai.php`. It is never hardcoded.
- `.env` is git-ignored. Only `.env.example` (with an empty key) is committed.
- After changing `.env`, run `php artisan config:clear` and restart `php artisan serve`.

---

## Assignment 1: AI-Assisted Text Classification API

Classifies a text into one of: **Complaint**, **Query**, **Feedback**, **Other**.

### Endpoint
`POST /api/classify`

### Request
```json
{
  "text": "Loved the new app design, it would be great to have a dark mode."
}
```

### Success response (200)
```json
{
  "success": true,
  "data": {
    "category": "Feedback",
    "confidence": 0.99
  }
}
```

### More examples
| Input text | Category |
|-----------|----------|
| `My order arrived broken` | Complaint |
| `How do I reset my password?` | Query |
| `Loved the new app design, it would be great to have a dark mode.` | Feedback |

### cURL
```bash
curl -X POST http://127.0.0.1:8000/api/classify \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"text": "My order arrived broken"}'
```

### How AI was used
1. The input text is sent to the Groq LLM together with a system prompt that defines the four
   allowed categories and instructs the model to return only a small JSON object
   containing `category` and `confidence` (0 to 1).
2. The service parses the JSON returned by the model.
3. The category is validated against the allowed list. An unexpected category falls back to `Other`.
4. The AI returns its own confidence score. If it is missing or invalid, a default
   mapping is applied so the API always returns a confidence value.

---

## Assignment 2: AI-Powered Email Template Generator

Generates a short, customer-friendly email template from a few input fields.

### Endpoint
`POST /api/generate-email`

### Request
```json
{
  "purpose": "Apologize for a delayed order",
  "recipient_name": "Rahul",
  "tone": "apologetic"
}
```

| Field | Rules |
|-------|-------|
| `purpose` | required, string, 3 to 500 characters |
| `recipient_name` | required, string, 1 to 100 characters |
| `tone` | required, one of: `formal`, `friendly`, `apologetic`, `persuasive`, `professional`, `casual`, `empathetic` |

### Success response (200)
```json
{
  "success": true,
  "data": {
    "subject": "Apology for Your Delayed Order",
    "body": "Dear Rahul,\n\nI am writing to sincerely apologize for the delay in delivering your order. ...\n\nBest regards,\n[Your Name]",
    "response_time_ms": 1334
  }
}
```

### cURL
```bash
curl -X POST http://127.0.0.1:8000/api/generate-email \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"purpose":"Apologize for a delayed order","recipient_name":"Rahul","tone":"apologetic"}'
```

### How the AI prompt was designed
- **System prompt** fixes the rules so the output is consistent and easy to parse:
  - keep the email under 120 words but always complete,
  - match the requested tone exactly,
  - address the recipient by name,
  - first line must be `Subject: <subject>`, followed by a blank line and the body,
  - end with a polite closing and the placeholder `[Your Name]`,
  - output only the email (no explanations, no markdown).
- **User prompt** only passes the dynamic inputs: purpose, recipient name and tone.
  Keeping variable data separate from the rules makes the behaviour predictable.
- `temperature = 0.7` gives natural wording without drifting off-topic, and
  `max_tokens = 700` avoids truncated emails.
- The service parses the `Subject:` line out of the AI text and returns `subject` and `body` separately.

### Response time logs
Every AI call is timed (timestamp difference) and written to a dedicated log channel
at `storage/logs/ai.log`. Each entry contains the provider, model, start time, finish
time, `response_time_ms` and the HTTP status. Failed calls log the status and response body.

Example log entry:
```
PASTE ONE REAL LINE FROM storage/logs/ai.log HERE
```
`response_time_ms` is also returned in the API response.

---

## Error Handling

| Case | Status | Response |
|------|--------|----------|
| Invalid or missing input | 422 | `{"success": false, "message": "Validation failed.", "errors": {...}}` |
| AI provider error, timeout or empty AI response | 500 | `{"success": false, "message": "..."}` (generic message) |

Internal details (stack traces, provider response bodies) are never returned to the client.
They are written to `storage/logs/laravel.log` and `storage/logs/ai.log`.

Always send the header `Accept: application/json` so Laravel returns JSON errors.

---

## Postman Collection
Import `postman/laravel-assignment.postman_collection.json` into Postman. It contains requests for:
- `POST /api/classify`
- `POST /api/generate-email`

Base URL: `http://127.0.0.1:8000`

---

## How AI Was Used in This Project
- **In the product:** the Groq LLM performs the text classification (Assignment 1) and writes the
  email templates (Assignment 2).
- **In development:** an AI assistant was used for code review, debugging and drafting documentation.
  All code was tested manually through Postman.