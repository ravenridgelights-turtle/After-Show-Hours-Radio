"""Small MPD protocol fixture for deterministic radio control tests."""
import json, os, shlex, socket, sys
path, history = sys.argv[1:]
server = socket.socket(socket.AF_INET); server.bind(('127.0.0.1', 0)); server.listen(20)
with open(path, 'w') as ready: ready.write(str(server.getsockname()[1]))
queue = []; next_id = 1; current = None; state = 'stop'; volume = 70
while True:
    conn, _ = server.accept()
    with conn:
        conn.sendall(b'OK MPD 0.23.12\n'); f = conn.makefile('rb')
        for raw in f:
            line = raw.decode().strip()
            with open(history, 'a') as log: log.write(json.dumps(line)+'\n')
            args = shlex.split(line); name = args[0]; response = ''
            if name == 'status':
                response = f'volume: {volume}\nstate: {state}\nplaylistlength: {len(queue)}\nelapsed: 10.0\n'
                if current is not None: response += f'songid: {current}\n'
            elif name == 'currentsong':
                song = next((s for s in queue if s[0] == current), None)
                if song: response = f'Id: {current}\nfile: {song[1]}\nTitle: Fixture title\n'
            elif name == 'addid':
                if 'reject.example' in args[1]: conn.sendall(b'ACK [50@0] {addid} rejected URL\n'); continue
                queue.append((next_id, args[1])); response = f'Id: {next_id}\n'; next_id += 1
            elif name == 'playid': current = int(args[1]); state = 'play'
            elif name == 'stop': state = 'stop'
            elif name == 'setvol': volume = int(args[1])
            elif name == 'deleteid': queue = [s for s in queue if s[0] != int(args[1])]
            elif name == 'delete':
                start, end = map(int, args[1].split(':')); del queue[start:end]
            elif name not in ('repeat','single','random'): conn.sendall(b'ACK [5@0] {} unknown command\n'); continue
            conn.sendall((response + 'OK\n').encode())
