"""
app.py – Code Judge Flask microservice.
POST /run      — Execute code against test cases
GET  /health   — Health check
POST /report/teacher — Generate teacher class PDF report
POST /report/student — Generate student personal PDF report
"""

import io
from flask import Flask, request, jsonify, send_file
from runner import judge_test_cases
from report_generator import generate_teacher_report, generate_student_report

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

    if not code:
        return jsonify({"error": "code field is required"}), 400
    if language not in ALLOWED_LANGUAGES:
        return jsonify({"error": f"Unsupported language: {language}"}), 400
    if not isinstance(test_cases, list) or len(test_cases) == 0:
        return jsonify({"error": "test_cases must be a non-empty list"}), 400

    try:
        results = judge_test_cases(language, code, test_cases)
        return jsonify({"results": results})
    except Exception as exc:
        app.logger.exception("Judge error")
        return jsonify({"error": f"Internal judge error: {str(exc)}"}), 500


@app.route("/report/teacher", methods=["POST"])
def report_teacher():
    if not request.is_json:
        return jsonify({"error": "Content-Type must be application/json"}), 400
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "Invalid JSON"}), 400
    try:
        pdf_bytes = generate_teacher_report(data)
        return send_file(
            io.BytesIO(pdf_bytes),
            mimetype='application/pdf',
            as_attachment=True,
            download_name='class_report.pdf'
        )
    except Exception as e:
        app.logger.exception("Teacher report error")
        return jsonify({"error": str(e)}), 500


@app.route("/report/student", methods=["POST"])
def report_student():
    if not request.is_json:
        return jsonify({"error": "Content-Type must be application/json"}), 400
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "Invalid JSON"}), 400
    try:
        pdf_bytes = generate_student_report(data)
        return send_file(
            io.BytesIO(pdf_bytes),
            mimetype='application/pdf',
            as_attachment=True,
            download_name='my_report.pdf'
        )
    except Exception as e:
        app.logger.exception("Student report error")
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)