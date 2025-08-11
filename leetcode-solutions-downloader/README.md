# LeetCode Accepted Submissions Downloader

This Python script downloads all your *accepted* LeetCode submissions in all languages you have solved, along with the problem descriptions, and organizes them locally in a clean folder structure.

---

## Features

- Authenticates with LeetCode using your session cookies.
- Fetches all accepted submissions.
- Downloads all solution source codes, keeping multiple solutions per problem & language.
- Downloads problem descriptions (saved as `README.md`).
- Organizes files as:

```
output_dir/
└── problem/
├──── README.md
├──── java/
│     ├── solution_1.java
│     └── solution_2.java
└──── rust/
      └── solution_1.rs
```

## Setup

### Clone this repository

```bash
git clone https://github.com/yourusername/leetcode-downloader.git
cd leetcode-downloader

cp config.example.json config.json
```

### Create and edit your config file

Edit `config.json` and fill in your:

`leetcode_session`: Your LeetCode session cookie
`csrftoken`: Your CSRF token cookie
`output_dir`: Directory path where solutions will be saved

### Run the script

```bash
python leetcode-downloader.py
```

## Notes

The script relies on LeetCode’s current GraphQL API.
If LeetCode changes their API, the script may need updates.
Use responsibly to avoid hitting API rate limits.

