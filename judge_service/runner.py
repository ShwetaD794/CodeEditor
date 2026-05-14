"""
runner.py - Executes student code inside isolated Docker containers.
"""
import subprocess
import tempfile
import os
import re

DOCKER_IMAGES = {
    "python": "python:3.10-slim",
    "c":      "gcc:latest",
    "cpp":    "gcc:latest",
    "java":   "eclipse-temurin:17-jdk",
}

SOURCE_FILES = {
    "python": "solution.py",
    "c":      "solution.c",
    "cpp":    "solution.cpp",
    "java":   "Solution.java",
}

COMPILE_CMDS = {
    "python": None,
    "c":      "gcc -o solution solution.c -lm",
    "cpp":    "g++ -o solution solution.cpp",
    "java":   "javac Solution.java",
}

RUN_CMDS = {
    "python": "python3 solution.py",
    "c":      "./solution",
    "cpp":    "./solution",
    "java":   "java Solution",
}

TIMEOUT_SECONDS = 10
MEMORY_LIMIT    = "128m"


def run_in_docker(language: str, code: str, stdin_data: str) -> dict:
    image = DOCKER_IMAGES.get(language)
    if not image:
        return {"stdout": "", "stderr": "", "error": f"Unsupported language: {language}"}

    source_file = SOURCE_FILES[language]
    compile_cmd = COMPILE_CMDS.get(language)
    run_cmd     = RUN_CMDS[language]

    with tempfile.TemporaryDirectory() as tmpdir:
        # Fix Java class name
        if language == "java":
            code = re.sub(r'public\s+class\s+\w+', 'public class Solution', code)

        src_path = os.path.join(tmpdir, source_file)
        with open(src_path, "w") as f:
            f.write(code)

        # Compile step
        if compile_cmd:
            compile_result = _docker_exec(
                image=image,
                tmpdir=tmpdir,
                cmd=compile_cmd,
                stdin_data="",
                timeout=30,
            )
            if compile_result["returncode"] != 0:
                return {
                    "stdout": "",
                    "stderr": compile_result["stderr"],
                    "error":  "Compilation error: " + compile_result["stderr"],
                }

        # Run step
        run_result = _docker_exec(
            image=image,
            tmpdir=tmpdir,
            cmd=run_cmd,
            stdin_data=stdin_data,
            timeout=TIMEOUT_SECONDS,
        )

        if run_result.get("timed_out"):
            return {"stdout": "", "stderr": "", "error": "Time limit exceeded"}

        return {
            "stdout": run_result["stdout"],
            "stderr": run_result["stderr"],
            "error":  None,
        }


def _docker_exec(image: str, tmpdir: str, cmd: str, stdin_data: str, timeout: int) -> dict:
    docker_cmd = [
        "docker", "run",
        "--rm",
        "-i",
        "--network", "none",
        "--memory",  MEMORY_LIMIT,
        "--cpus",    "0.5",
        "--pids-limit", "64",
        "--read-only",
        "--tmpfs", "/tmp:size=64m",
        "--tmpfs", "/root:size=64m",
        "-v", f"{tmpdir}:/workspace:rw",
        "-w", "/workspace",
        image,
        "sh", "-c", cmd,
    ]

    try:
        proc = subprocess.run(
            docker_cmd,
            input=stdin_data.encode("utf-8"),
            capture_output=True,
            timeout=timeout + 2,
        )
        return {
            "returncode": proc.returncode,
            "stdout":     proc.stdout.decode("utf-8", errors="replace"),
            "stderr":     proc.stderr.decode("utf-8", errors="replace"),
            "timed_out":  False,
        }
    except subprocess.TimeoutExpired:
        return {"returncode": -1, "stdout": "", "stderr": "", "timed_out": True}
    except FileNotFoundError:
        return {"returncode": -1, "stdout": "", "stderr": "Docker not found on host.", "timed_out": False}


def judge_test_cases(language: str, code: str, test_cases: list) -> list:
    results = []
    for tc in test_cases:
        expected = str(tc.get("expected_output", "")).strip()
        stdin    = str(tc.get("input", "") or "")

        run_result = run_in_docker(language, code, stdin)

        if run_result["error"]:
            student_output = run_result["error"]
            verdict        = "FAIL"
        else:
            student_output = run_result["stdout"].strip()
            verdict        = "PASS" if student_output == expected else "FAIL"

        results.append({
            "input":           stdin,
            "student_output":  student_output,
            "expected_output": expected,
            "result":          verdict,
        })

    return results
