import type { CSSProperties } from 'react';

type Props = {
  headColor?: string;
  bodyColor?: string;
  scale?: number; // 1 = default size, everything inside is em-based
  facing?: 'left' | 'right';
  shadow?: boolean; // ground shadow under the sheep
};

const css = `
.im-sheep { display:inline-block; position:relative; isolation:isolate; font-size:1em; margin-bottom:4.4em }
.im-sheep * { transition:transform .3s }
.im-sheep:before { content:""; position:absolute; top:112%; left:0; width:100%; height:18%; border-radius:100%; background:rgba(0,0,0,.2) }
.im-sheep.no-shadow:before { display:none }
.im-sheep .top { position:relative; top:0; animation:bob 1s infinite }
.im-sheep:hover .top { animation-play-state:paused }
/* wool: one circle + box-shadow puffs around its edge */
.im-sheep .body { display:inline-block; vertical-align:middle; position:relative; width:6.4em; height:6em; margin-right:-2.2em; border-radius:100%; background:var(--sheep-body);
  box-shadow:
    -0.3em -1.9em 0 -1.2em var(--sheep-body),
    -1.9em -1.4em 0 -1.4em var(--sheep-body),
    -2.6em 0.1em 0 -1.5em var(--sheep-body),
    -2em 1.5em 0 -1.6em var(--sheep-body),
    -0.3em 2em 0 -1.4em var(--sheep-body),
    1.4em 1.7em 0 -1.6em var(--sheep-body),
    1.8em -1.7em 0 -1.5em var(--sheep-body) }
.im-sheep .head { display:inline-block; vertical-align:middle; position:relative; top:1.1em; width:4.2em; height:4.4em; border-radius:55% 45% 48% 52%; background:var(--sheep-head); transform:rotate(12deg) }
/* muzzle */
.im-sheep .head:before { content:""; position:absolute; right:-0.5em; bottom:-0.2em; width:2.8em; height:2.4em; border-radius:60% 50% 55% 45%; background:var(--sheep-head) }
/* wool fringe over the forehead */
.im-sheep .head:after { content:""; position:absolute; top:-0.8em; left:0.1em; width:3.4em; height:1.9em; border-radius:100%; background:var(--sheep-body);
  box-shadow: -0.9em 0.5em 0 -0.5em var(--sheep-body), 1em 0.4em 0 -0.4em var(--sheep-body) }
.im-sheep:hover .head { transform:rotate(0deg) }
.im-sheep .im-eye { position:absolute; top:1.9em; width:.9em; height:.9em; border-radius:100%; background:#fff; overflow:hidden; box-shadow:0 0 0 .12em rgba(0,0,0,.25) }
.im-sheep .im-eye:before { content:""; position:absolute; right:15%; bottom:15%; width:55%; height:55%; border-radius:100%; background:#000; transition:all .3s }
.im-sheep .im-eye.one { right:.5em }
.im-sheep .im-eye.two { right:2.1em }
.im-sheep:hover .im-eye { width:1.15em; height:1.15em }
.im-sheep:hover .im-eye:before { right:35% }
.im-sheep .im-ear { position:absolute; width:1.9em; height:.9em; border-radius:100%; background:var(--sheep-head) }
.im-sheep .im-ear.one { top:1.1em; left:-1.2em; transform:rotate(-18deg) }
.im-sheep .im-ear.two { top:.3em; right:-1em; transform:rotate(22deg) }
.im-sheep .head:hover .im-ear { transform:rotate(0deg) }
.im-sheep .im-legs { position:absolute; top:78%; left:8%; z-index:-1 }
.im-sheep .im-leg { display:inline-block; width:.5em; height:2.5em; margin:.2em; background:#141214; border-radius:.25em .25em .1em .1em }
@keyframes bob { 0% { top:0 } 50% { top:.3em } }
`;

export default function Sheep({
  headColor = '#2d2f30',
  bodyColor = '#919191',
  scale = 1,
  facing = 'right',
  shadow = true,
}: Props) {
  return (
    <div className="error-image">
      <style>{css}</style>
      <div
        className={shadow ? 'im-sheep' : 'im-sheep no-shadow'}
        style={
          {
            '--sheep-head': headColor,
            '--sheep-body': bodyColor,
            fontSize: `${scale}em`,
            transform: facing === 'left' ? 'scaleX(-1)' : undefined,
          } as CSSProperties
        }
      >
        <div className="top">
          <div className="body"></div>
          <div className="head">
            <div className="im-eye one"></div>
            <div className="im-eye two"></div>
            <div className="im-ear one"></div>
            <div className="im-ear two"></div>
          </div>
        </div>
        <div className="im-legs">
          <div className="im-leg"></div>
          <div className="im-leg"></div>
          <div className="im-leg"></div>
          <div className="im-leg"></div>
        </div>
      </div>
    </div>
  );
}
