# Direct Strategy: Current Status

This file replaces older implementation-plan notes.

## Current Status

- strategy class exists and is registered in `MemberService`
- direct mode is the default strategy
- add flow supports explicit membership override through request context
- direct mode uses the shared queued bulk-upload pipeline when enabled
