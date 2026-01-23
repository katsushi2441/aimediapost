# AIMediaPost

AIMediaPost is an AI-powered media posting tool that transforms text-based posts
into audio-rich, shareable media content with background music.

It allows creators to publish **voice-based posts** without recording,
editing, or complex production workflows.

---

## What this project does

AIMediaPost provides a simple, end-to-end workflow for audio-first social posts.

Users can:

- Write or paste a text post (news, commentary, opinion, narration)
- Convert the text into AI-generated speech (text-to-speech)
- Mix the generated voice with background music
- Preview and play the resulting audio
- Use the output for SNS posts, radio-style content, or media publishing

All steps are performed from a single web interface.

---

## Outputs (What users get)

- AI-generated narration audio
- Background-music–mixed audio
- Ready-to-post audio content for SNS
- Built-in audio preview player

---

## Key features

- Text-based post editor
- One-click AI text-to-speech
- Background music selection and mixing
- Start time and volume adjustment for BGM
- Audio preview and playback
- Mobile-friendly UI
- No microphone required
- No manual recording or editing

---

## Typical use cases

- Social commentary and opinion posts
- News summaries and issue explanations
- Radio-style short programs
- Audio-first SNS content
- Anonymous or faceless media publishing

---

## Philosophy

AIMediaPost is built on a simple idea:

> If you can write it, you can broadcast it.

The tool lowers the barrier between **thought** and **media**,
allowing individuals to turn written ideas into voice-based content instantly.

---

## Architecture overview

AIMediaPost is intentionally designed to be simple and lightweight.

- The entire UI and orchestration logic are implemented in PHP
- Text-to-speech is handled via an external or local AI backend
- Background music mixing is triggered directly from the UI

All functionality is coordinated from a single entry point.

---

## Extensibility

Although minimal by design, AIMediaPost can be extended to:

- Swap text-to-speech engines
- Add new background music sources
- Integrate audio-to-video pipelines
- Connect external publishing or automation workflows

---

## Repository structure

.
├── aimediapost.php  # Main UI, posting logic, audio generation and BGM mixing
└── README.md

---

## Requirements

- PHP-compatible web server
- AI text-to-speech backend (external or local)
- ffmpeg (for audio processing)

---

## License

MIT License

