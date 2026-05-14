"""
app.py – Code Judge Flask microservice.
Receives code + test cases from Moodle PHP, executes inside Docker,
and returns structured results.

Usage:
    pip install flask
    python app.py

Endpoint:
    POST /run
    Content-Type: application/json
    Body: {"code": "...", "language": "python", "test_cases": [...]}
"""

from flask import Flask, request, jsonify
from runner import judge_test_cases

app = Flask(__name__)

ALLOWED_LANGUAGES = {"python", "c", "cpp", "java"}


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/run", methods=["POST"])
def run():
    if not request.is_json:
        return jsonify({"error": "Content-Type must be application/json"}), 400

    data = request.get_json(silent=True)
    if data is None:
        return jsonify({"error": "Invalid JSON body"}), 400

    code       = data.get("code", "").strip()
    language   = str(data.get("language", "")).lower().strip()
    test_cases = data.get("test_cases", [])

    # --- Validation ---
    if not code:
        return jsonify({"error": "code field is required"}), 400

    if language not in ALLOWED_LANGUAGES:
        return jsonify({"error": f"Unsupported language: {language}. Allowed: {sorted(ALLOWED_LANGUAGES)}"}), 400

    if not isinstance(test_cases, list) or len(test_cases) == 0:
        return jsonify({"error": "test_cases must be a non-empty list"}), 400

    # --- Execute ---
    try:
        results = judge_test_cases(language, code, test_cases)
        return jsonify({"results": results})
    except Exception as exc:
        app.logger.exception("Judge error")
        return jsonify({"error": f"Internal judge error: {str(exc)}"}), 500


if __name__ == "__main__":
    # Run on all interfaces so it's reachable from Moodle container/host
    app.run(host="0.0.0.0", port=5000, debug=False)
