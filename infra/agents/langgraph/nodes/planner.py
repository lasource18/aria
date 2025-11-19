# planner.py
from typing import List, Tuple

def plan(idea: str) -> Tuple[str, List[str]]:
    spec = f"# Generated Spec\nIdea: {idea}\nStories: ..."
    tasks = ["Scaffold app", "Search screen", "Offline save"]
    return spec, tasks
