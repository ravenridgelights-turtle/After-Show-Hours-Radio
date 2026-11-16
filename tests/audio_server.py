"""Serve local WAV fixtures for the real-MPD integration test."""
import http.server, pathlib, sys
root, ready = sys.argv[1:]
class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs): super().__init__(*args, directory=root, **kwargs)
    def log_message(self, *args): pass
server = http.server.HTTPServer(('127.0.0.1', 0), Handler)
pathlib.Path(ready).write_text(str(server.server_port)); server.serve_forever()
