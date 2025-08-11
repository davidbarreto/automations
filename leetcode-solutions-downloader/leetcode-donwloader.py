"""
LeetCode Accepted Submissions Downloader
-----------------------------------------
This script:
    - Logs into LeetCode using cookies from a config.json file.
    - Fetches all *accepted* submissions for the logged-in account.
    - Downloads each solution's source code for *all languages* you've solved in.
    - Downloads the problem description (as README.md) for each unique problem.
    - Organizes everything by problem -> language -> multiple solution files.

Config file (config.json) format:
{
    "leetcode_session": "your_leetcode_session_cookie",
    "csrftoken": "your_csrftoken_cookie",
    "output_dir": "path/to/save/solutions"
}
"""

import os
import re
import json
from pathlib import Path
from typing import List, Dict, Optional
from collections import defaultdict

import requests

# ----------------------------
# Load configuration
# ----------------------------
CONFIG_PATH = Path(__file__).parent / "config.json"

def load_config(config_path: Path) -> Dict[str, str]:
    """
    Load configuration from JSON file.
    Raises an error if file is missing.
    """
    if not config_path.exists():
        raise FileNotFoundError(f"Config file not found: {config_path}")
    with open(config_path, "r", encoding="utf-8") as f:
        return json.load(f)


# ----------------------------
# Utility helpers
# ----------------------------
def slugify(text: str) -> str:
    """
    Convert problem slugs into safe folder names.
    Example: "Two Sum" -> "two-sum"
    """
    return re.sub(r'[^a-z0-9]+', '-', text.lower()).strip('-')


# ----------------------------
# LeetCode API interaction
# ----------------------------
def get_submissions(session: requests.Session) -> List[Dict]:
    """
    Fetch all accepted submissions from LeetCode using GraphQL.
    Filters accepted submissions client-side since server no longer supports 'status' arg.
    """
    submissions = []
    has_next = True
    offset = 0
    limit = 20

    def run_query(query_body):
        resp = session.post("https://leetcode.com/graphql", json=query_body)
        resp.raise_for_status()
        return resp.json()

    while has_next:
        query = {
            "operationName": "submissionList",
            "variables": {
                "offset": offset,
                "limit": limit,
            },
            "query": """
            query submissionList($offset: Int!, $limit: Int!) {
              submissionList(offset: $offset, limit: $limit) {
                hasNext
                submissions {
                  id
                  title
                  titleSlug
                  lang
                  statusDisplay
                  timestamp
                }
              }
            }
            """
        }

        data = run_query(query)
        sublist = data["data"]["submissionList"]["submissions"]
        has_next = data["data"]["submissionList"]["hasNext"]

        # Filter client-side for accepted only
        accepted_subs = [s for s in sublist if s.get("statusDisplay") == "Accepted"]

        submissions.extend(accepted_subs)
        offset += limit

    return submissions

def get_submission_code(session: requests.Session, submission_id: int) -> Optional[str]:
    """
    Download the submission's source code.
    NOTE: This scrapes HTML, because LeetCode's GraphQL doesn't expose the code.
    Returns None if code not found.
    """
    resp = session.get(f"https://leetcode.com/submissions/detail/{submission_id}/")

    # Code is inside a JS variable: submissionCode: '...'
    match = re.search(r"submissionCode:\s*'(.*?)',\s*editCodeUrl", resp.text, re.S)
    if match:
        code = match.group(1)
        # Decode Unicode escapes (e.g., \u000A for newlines)
        return code.encode('utf-8').decode('unicode_escape')
    return None


def download_problem_description(session: requests.Session, title_slug: str, output_dir: Path):
    """
    Download the problem description and save it as README.md in Markdown format.
    HTML tags are stripped (basic clean-up).
    """
    query = {
        "operationName": "questionData",
        "variables": {"titleSlug": title_slug},
        "query": """
        query questionData($titleSlug: String!) {
          question(titleSlug: $titleSlug) {
            questionId
            title
            content
            difficulty
            titleSlug
          }
        }
        """
    }
    resp = session.post("https://leetcode.com/graphql", json=query)
    resp.raise_for_status()
    data = resp.json()
    q = data["data"]["question"]

    # Build Markdown description
    description_md = (
        f"# {q['title']} (Difficulty: {q['difficulty']})\n\n"
        f"[LeetCode Link](https://leetcode.com/problems/{q['titleSlug']}/)\n\n"
        f"---\n\n"
        f"{q['content']}\n"
    )

    # Convert HTML code blocks to Markdown style
    description_md = re.sub(r'<pre>(.*?)</pre>', r'```\1```', description_md, flags=re.DOTALL)
    # Remove all other HTML tags
    description_md = re.sub(r'<[^>]+>', '', description_md)

    with open(output_dir / "README.md", "w", encoding="utf-8") as f:
        f.write(description_md)


# ----------------------------
# Main script logic
# ----------------------------
def main():
    # Load settings
    config = load_config(CONFIG_PATH)
    output_dir = Path(config["output_dir"])
    output_dir.mkdir(exist_ok=True)

    # Create authenticated session
    session = requests.Session()
    session.cookies.set("LEETCODE_SESSION", config["leetcode_session"])
    session.cookies.set("csrftoken", config["csrftoken"])
    session.headers.update({
        "x-csrftoken": config["csrftoken"],
        "referer": "https://leetcode.com/problemset/all/",  # More specific referer
        "Content-Type": "application/json",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0 Safari/537.36"
    })
    
    if not check_logged_in(session):
        raise RuntimeError("Authentication failed: Please check your LEETCODE_SESSION and csrftoken cookies in config.json")

    # Get all accepted submissions
    submissions = get_submissions(session)
    print(f"Found {len(submissions)} accepted submissions.")
    all_langs = set(sub["lang"] for sub in submissions)
    print("Languages found in your submissions:", all_langs)

    # Organize submissions: {problem_slug: {language: [submissions...]}}
    problems = defaultdict(lambda: defaultdict(list))
    for sub in submissions:
        problems[slugify(sub["titleSlug"])][sub["lang"]].append(sub)

    # File extensions for known languages
    ext_map = {
        "java": ".java",
        "python": ".py",
        "python3": ".py",
        "c": ".c",
        "c++": ".cpp",
        "rust": ".rs",
        "go": ".go",
        "javascript": ".js",
        "typescript": ".ts",
        "kotlin": ".kt",
        "scala": ".scala",
    }

    # Loop through problems and download everything
    for problem_slug, langs in problems.items():
        # Use first submission's titleSlug for description download
        first_lang = next(iter(langs))
        first_submission = langs[first_lang][0]
        title_slug = first_submission["titleSlug"]

        # Create problem folder
        problem_dir = output_dir / problem_slug
        problem_dir.mkdir(exist_ok=True)

        # Save problem description
        download_problem_description(session, title_slug, problem_dir)

        # Loop over languages
        for lang, submissions in langs.items():
            # Create language-specific folder (C++ → cpp)
            lang_dir = problem_dir / lang.lower().replace("++", "pp")
            lang_dir.mkdir(exist_ok=True)

            # Sort by timestamp (most recent first)
            submissions.sort(key=lambda x: int(x["timestamp"]), reverse=True)

            # Download each submission
            for idx, sub in enumerate(submissions, start=1):
                code = get_submission_code(session, sub["id"])
                if not code:
                    continue
                ext = ext_map.get(sub["lang"], ".txt")
                filename = f"solution_{idx}{ext}"
                with open(lang_dir / filename, "w", encoding="utf-8") as f:
                    f.write(code)
                print(f"Saved {sub['title']} ({sub['lang']}) as {filename}")

def check_logged_in(session: requests.Session) -> bool:
    resp = session.get("https://leetcode.com/api/problems/all/")
    if resp.status_code != 200:
        return False
    data = resp.json()
    return "user_name" in data and data["user_name"] is not None

if __name__ == "__main__":
    main()
