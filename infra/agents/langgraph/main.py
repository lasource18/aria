"""Minimal LangGraph-ish stub: orchestrates role prompts and writes files/issues.
Replace with real LangGraph or framework of choice.
"""
from pathlib import Path
from nodes.planner import plan
from nodes.architect import architect
from nodes.implementer import implement
from nodes.reviewer import review
from nodes.qa import qa

ROOT = Path(__file__).resolve().parents[3]

def run_idea_to_tasks(idea_text: str):
    spec, tasks = plan(idea_text)
    (ROOT/"docs/product_specs/GENERATED_FROM_IDEA.md").write_text(spec)
    # TODO: Create GitHub issues via API
    return tasks

def run_pr_cycle(diff: str):
    suggestions = review(diff)
    qa_report = qa()
    return suggestions, qa_report

if __name__ == "__main__":
    tasks = run_idea_to_tasks("Build MealMuse MVP")
    print("Planned tasks:", tasks)
