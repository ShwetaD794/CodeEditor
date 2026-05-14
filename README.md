# local_codejudge – Moodle Code Judge Plugin

A multi-language, Docker-based coding assessment plugin for Moodle.

---

## Folder Structure

```
local/codejudge/               ← Moodle plugin root
├── version.php
├── index.php                  ← Student question list
├── view.php
├── submit.php                 ← AJAX submission endpoint
├── lib.php
├── settings.php
├── db/
│   ├── install.xml            ← DB schema (5 tables)
│   ├── install.php            ← Seeds default languages
│   └── access.php             ← Capabilities
├── classes/
│   ├── question_manager.php
│   ├── submission_manager.php
│   └── report_manager.php
├── teacher/
│   ├── dashboard.php
│   ├── add_question.php
│   ├── edit_question.php
│   ├── delete_question.php
│   ├── add_testcase.php
│   └── delete_testcase.php
├── student/
│   ├── attempt.php            ← Ace editor + language selector
│   └── result.php
├── lang/en/local_codejudge.php
├── amd/src/editor.js
└── judge_service/             ← Python Flask microservice
    ├── app.py
    ├── runner.py
    ├── requirements.txt
    ├── Dockerfile
    └── docker-compose.yml
```

---

## Moodle Plugin Installation

1. Copy the `local/codejudge/` folder to `<moodle_root>/local/codejudge/`.
2. Log in as Moodle admin → **Site administration → Notifications** to trigger DB install.
3. Go to **Site administration → Plugins → Local plugins → Code Judge** to set the judge URL.

---

## Judge Microservice Setup

### Option A – Docker Compose (recommended)

```bash
cd local/codejudge/judge_service/
docker-compose up -d
```

### Option B – Direct Python

```bash
cd local/codejudge/judge_service/
pip install -r requirements.txt
python app.py
# Service starts on http://0.0.0.0:5000
```

### Pull required Docker images

```bash
docker pull python:3.10-slim
docker pull gcc:latest
docker pull openjdk:17-slim
```

---

## Moodle Admin Settings

Path: **Site admin → Plugins → Local plugins → Code Judge**

| Setting | Default | Description |
|---------|---------|-------------|
| Judge service URL | `http://localhost:5000/run` | Python judge endpoint |
| Execution timeout | `5` | Seconds before kill |

---

## API – Judge Microservice

### Request

```
POST http://localhost:5000/run
Content-Type: application/json
```

```json
{
  "code": "print(int(input()) + int(input()))",
  "language": "python",
  "test_cases": [
    { "id": 1, "input": "3\n5", "expected_output": "8" },
    { "id": 2, "input": "10\n20", "expected_output": "30" }
  ]
}
```

### Response

```json
{
  "results": [
    {
      "input": "3\n5",
      "student_output": "8",
      "expected_output": "8",
      "result": "PASS"
    },
    {
      "input": "10\n20",
      "student_output": "30",
      "expected_output": "30",
      "result": "PASS"
    }
  ]
}
```

---

## Docker Execution (per test case)

```bash
docker run --rm \
  --network none \
  --memory 128m \
  --cpus 0.5 \
  --pids-limit 64 \
  --read-only \
  --tmpfs /tmp:size=64m \
  -v /tmp/codejudge_XXXX:/workspace:rw \
  -w /workspace \
  python:3.10-slim \
  sh -c "python3 solution.py"
```

**Security flags:**
- `--network none` – no internet access
- `--memory 128m` – memory cap
- `--cpus 0.5` – CPU cap
- `--read-only` – immutable filesystem
- `--pids-limit 64` – fork-bomb protection
- `--rm` – auto-cleanup

---

## Capabilities

| Capability | Role |
|-----------|------|
| `local/codejudge:managequestions` | Teacher, Manager |
| `local/codejudge:submit` | Student |
| `local/codejudge:viewreports` | Teacher, Manager |
| `local/codejudge:viewown` | Student |

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `codejudge_questions` | Question bank |
| `codejudge_test_cases` | Test cases per question |
| `codejudge_languages` | Language config + boilerplate |
| `codejudge_submissions` | Student code submissions |
| `codejudge_reports` | Per-test-case pass/fail detail |

---

## Supported Languages

| Language | Compile | Run | Image |
|----------|---------|-----|-------|
| Python | – | `python3 solution.py` | python:3.10-slim |
| C | `gcc -o solution solution.c` | `./solution` | gcc:latest |
| C++ | `g++ -o solution solution.cpp` | `./solution` | gcc:latest |
| Java | `javac Solution.java` | `java Solution` | openjdk:17-slim |
