---
name: anti-overchecking
description: >-
  Prevents overchecking, excessive investigation, redundant inspection loops, and unnecessary multi-step analysis on straightforward tasks. Use when modifying styles, assets, small UI tweaks, or direct user instructions where immediate execution is needed.
---

# Anti-Overchecking & Decisive Execution Protocol

## Purpose
This skill ensures the agent executes tasks decisively without falling into loops of over-analysis, repetitive inspecting, excessive diagnostic script runs, or multi-step re-verification when the user has given a direct instruction.

----

## Core Principles

### 1. Identify Direct, Focused Requests Immediately
When the user's request is specific (e.g., "change background color to white", "swap this image", "adjust font size", "make it transparent png"):
- **Do not investigate unnecessary peripheral context.**
- **Do not inspect unrelated files, formats, or processes.**
- **Identify the direct target line and apply the edit immediately.**

### 2. The Single-Check Rule
- When diagnosing an issue, perform **at most one focused inspection** to find the exact target.
- If the issue is already clear from the user's prompt or screenshot (e.g., a background color mismatch or card container style), proceed directly to modify the file without running diagnostic scripts.

### 3. Stop Redundant Post-Edit Validations
- Avoid launching browser subagents, repetitive command checks, or multi-tool verification sequences after simple CSS/HTML/JS edits unless explicitly requested by the user.
- Simple diff checks or file inspection is sufficient.

### 4. Direct Action Over Speculation
- If the user says "stop overchecking and fix it" or expresses impatience:
  1. Immediately stop any exploratory tools.
  2. Apply the minimal, exact code change requested.
  3. Respond concisely confirming the fix.
