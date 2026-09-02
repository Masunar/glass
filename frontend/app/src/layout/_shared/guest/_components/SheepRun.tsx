import { Box } from '@mui/material';

import Sheep from './Sheep';
import { useEffect, useRef, useState } from 'react';

/*
 * Chrome-dino style runner: the sheep jumps the fences.
 * Positions live in refs and are written straight to the DOM — React only
 * tracks the phase, so the loop never re-renders.
 */

const GROUND = 26; // px from the bottom of the field
const SHEEP_X = 40;
const SHEEP_W = 42;
const GRAVITY = 2000;
const JUMP = 640;
const START_SPEED = 260;

type Obstacle = { x: number; w: number; h: number };

const shapes: Obstacle[] = [
  { x: 0, w: 14, h: 26 }, // fence post
  { x: 0, w: 22, h: 18 }, // rock
  { x: 0, w: 28, h: 30 }, // bush
];

const spawn = (scale: number): Obstacle => {
  const shape = shapes[Math.floor(Math.random() * 3)];
  return { x: 0, w: shape.w * scale, h: shape.h * scale };
};

type Props = {
  height?: number;
  scale?: number; // scales the whole field: drawing and physics alike
};

export default function SheepRun({ height = 190, scale = 1 }: Props) {
  const ground = GROUND * scale;
  const sheepX = SHEEP_X * scale;
  const sheepW = SHEEP_W * scale;

  const [phase, setPhase] = useState<'idle' | 'running' | 'over'>('idle');

  const fieldRef = useRef<HTMLDivElement>(null);
  const sheepRef = useRef<HTMLDivElement>(null);
  const scoreRef = useRef<HTMLDivElement>(null);
  const obstacleRefs = useRef<(HTMLDivElement | null)[]>([]);

  const audioRef = useRef<AudioContext | null>(null);

  /* short synthesized blips — no assets to ship */
  const beep = (
    from: number,
    to: number,
    duration: number,
    type: OscillatorType,
  ) => {
    const Ctx = window.AudioContext ?? (window as any).webkitAudioContext;
    if (!Ctx) return;

    const ctx = (audioRef.current ??= new Ctx());
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    const now = ctx.currentTime;

    osc.type = type;
    osc.frequency.setValueAtTime(from, now);
    osc.frequency.exponentialRampToValueAtTime(to, now + duration * 0.75);
    gain.gain.setValueAtTime(0.05, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(now);
    osc.stop(now + duration + 0.01);
  };

  const playJump = () => beep(440, 780, 0.15, 'square');
  const playOuch = () => beep(320, 90, 0.4, 'sawtooth');

  useEffect(() => () => void audioRef.current?.close(), []);

  const state = useRef({
    y: 0,
    vy: 0,
    speed: START_SPEED,
    score: 0,
    obstacles: [] as Obstacle[],
    jump: () => {},
  });

  useEffect(() => {
    if (phase !== 'running') return;

    const game = state.current;
    const width = fieldRef.current?.clientWidth ?? 600;

    game.y = 0;
    game.vy = 0;
    game.speed = START_SPEED * scale;
    game.score = 0;
    game.obstacles = [spawn(scale), spawn(scale), spawn(scale)].map((o, i) => ({
      ...o,
      x: width + i * 320 * scale,
    }));

    game.jump = () => {
      if (game.y > 0) return;
      game.vy = JUMP * scale;
      playJump();
    };

    let raf = 0;
    let last = performance.now();

    const frame = (now: number) => {
      const dt = Math.min((now - last) / 1000, 0.05);
      last = now;

      game.vy -= GRAVITY * scale * dt;
      game.y = Math.max(0, game.y + game.vy * dt);
      if (game.y === 0) game.vy = 0;

      game.speed += 6 * scale * dt;
      game.score += dt * 10;

      let hit = false;
      game.obstacles.forEach((o, i) => {
        o.x -= game.speed * dt;
        if (o.x < -o.w) {
          const furthest = Math.max(...game.obstacles.map((p) => p.x));
          Object.assign(o, spawn(scale));
          o.x = furthest + (220 + Math.random() * 220) * scale;
        }

        const overlapX =
          o.x < sheepX + sheepW - 8 * scale && o.x + o.w > sheepX + 8 * scale;
        if (overlapX && game.y < o.h - 6 * scale) hit = true;

        const el = obstacleRefs.current[i];
        if (el) {
          el.style.transform = `translateX(${o.x}px)`;
          el.style.width = `${o.w}px`;
          el.style.height = `${o.h}px`;
        }
      });

      if (sheepRef.current) {
        sheepRef.current.style.transform = `translateY(${-game.y}px)`;
      }
      if (scoreRef.current) {
        scoreRef.current.textContent = String(Math.floor(game.score));
      }

      if (hit) {
        playOuch();
        setPhase('over');
        return;
      }
      raf = requestAnimationFrame(frame);
    };

    raf = requestAnimationFrame(frame);
    return () => cancelAnimationFrame(raf);
  }, [phase, scale]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.code !== 'Space' && e.code !== 'ArrowUp') return;
      e.preventDefault();
      if (phase === 'running') state.current.jump();
      else setPhase('running');
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [phase]);

  const poke = () => {
    if (phase === 'running') state.current.jump();
    else setPhase('running');
  };

  return (
    <Box
      ref={fieldRef}
      onPointerDown={poke}
      sx={{
        position: 'relative',
        height,
        overflow: 'hidden',
        borderRadius: 1,
        userSelect: 'none',
        cursor: 'pointer',
        background: 'linear-gradient(#eaf1fa, #f3f6f4)',
      }}
    >
      {/* ground */}
      <Box
        sx={{
          position: 'absolute',
          left: 0,
          right: 0,
          bottom: 0,
          height: ground,
          background: '#cadeb4',
          borderTop: '2px solid #b3cfa2',
        }}
      />

      <Box
        ref={scoreRef}
        sx={{
          position: 'absolute',
          top: 8,
          right: 12,
          fontSize: 13 * scale,
          fontWeight: 700,
          color: '#8a9bb0',
        }}
      >
        0
      </Box>

      <Box
        ref={sheepRef}
        sx={{ position: 'absolute', left: sheepX, bottom: ground - 13 * scale }}
      >
        <Sheep scale={0.34 * scale} shadow={false} />
      </Box>

      {[0, 1, 2].map((i) => (
        <Box
          key={i}
          ref={(el: HTMLDivElement | null) => {
            obstacleRefs.current[i] = el;
          }}
          sx={{
            position: 'absolute',
            left: 0,
            bottom: ground,
            borderRadius: '3px 3px 0 0',
            background: '#8c6a52',
          }}
        />
      ))}

      {phase !== 'running' && (
        <Box
          sx={{
            position: 'absolute',
            inset: 0,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '4px',
            fontSize: 14 * scale,
            color: '#5b6b82',
            background: 'rgba(255,255,255,.55)',
          }}
        >
          <Box sx={{ fontWeight: 700 }}>
            {phase === 'idle' ? 'Sheep run' : 'Ouch!'}
          </Box>
          <Box>space / click to {phase === 'idle' ? 'start' : 'try again'}</Box>
        </Box>
      )}
    </Box>
  );
}
