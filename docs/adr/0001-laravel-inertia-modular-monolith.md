# 1. Laravel 11 and Inertia.js Modular Monolith

Date: 2026-10-21

## Context

Nalar requires rich editor interaction (Tiptap), background document processing, citation management, and AI retrieval. The project is developed and maintained by a solo developer using AI coding agents.

## Decision

Build Nalar as a Laravel 11 modular monolith using Inertia.js (Vue 3) and Tiptap editor. Document extraction and OCR run in native Laravel queue workers utilizing native CLI tools (`pdftotext`, `tesseract-ocr`).

## Consequences

- Eliminates separate API protocol maintenance and dual repository overhead.
- Simplifies authentication, session handling, and database transactions across features.
- Enables rich Tiptap editor extensions natively in Vue 3.
- Advanced layout parsing via external Python services is deferred until proven necessary.
