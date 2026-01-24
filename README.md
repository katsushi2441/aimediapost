# AIMediaPost

AIMediaPost is an AI-powered media generation toolkit
for creating **audio-rich, social-ready posts** from text.

It focuses on transforming written content into
voice narration, background-music–mixed audio,
and shareable media assets without manual recording or editing.

---

## What this project does

AIMediaPost provides modular building blocks
for an audio-first media posting workflow.

It allows developers and creators to:

- Convert text posts into AI-generated voice narration
- Mix narration with background music
- Generate subtitle-based MP4 videos from audio
- Create ready-to-post text captions for social platforms

The project is designed to support
automated and repeatable media publishing workflows.

---

## Outputs (What you can generate)

- AI-generated narration audio
- Background-music–mixed audio
- Subtitle-based MP4 videos
- Social media post text (e.g. for X)

These outputs can be combined or used independently
depending on the publishing workflow.

---

## Key components

AIMediaPost is composed of small, focused generators.

- **voicegen.php**  
  Generates narration audio from text using a TTS backend.

- **mp4gen.php**  
  Converts audio and scripts into subtitle-based MP4 videos
  suitable for social platforms.

- **postgen.php**  
  Generates short-form post text for social platforms
  based on narration or script content.

- **voicevox_api.py**  
  Example backend implementation for text-to-speech processing.

Each component can be used independently
or orchestrated by an external UI or automation script.

---

## Typical use cases

- Audio-first social media posts
- Short-form narrated videos
- News summaries and commentary
- Radio-style micro content
- Faceless or anonymous media publishing
- Automated content pipelines

---

## Design philosophy

AIMediaPost is built on a simple idea:

> If you can write it, you can publish it as media.

The project emphasizes:

- Minimal manual work
- Clear separation of responsibilities
- Scriptable and automatable workflows
- Output-first design (audio, video, post text)

---

## Architecture overview

- Core generators are implemented in PHP
- Text-to-speech is handled by an external or local AI backend
- Media processing relies on standard tools (e.g. ffmpeg)
- No monolithic framework dependency

UI and orchestration layers are intentionally excluded
to keep the repository flexible and reusable.

---

## Extensibility

AIMediaPost can be extended to:

- Support multiple TTS engines
- Add narration style presets
- Integrate audio-to-video pipelines
- Connect social posting or automation systems
- Adapt outputs for different platforms

---

## Repository structure

```text
.
├── src/
│   ├── voicegen.php
│   ├── mp4gen.php
│   ├── postgen.php
│   └── voicevox_api.py
└── README.md

---

## Requirements

- PHP-compatible runtime
- AI text-to-speech backend (external or local)
- ffmpeg (for audio and video processing)

---

## License

MIT License

