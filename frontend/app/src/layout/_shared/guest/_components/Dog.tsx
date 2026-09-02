import type { CSSProperties } from 'react';

type Props = {
  coatColor?: string;
  scale?: number; // 1 = default size, everything inside is em-based
  facing?: 'left' | 'right';
};

const css = `
.im-dog { display:inline-block; position:relative; isolation:isolate; width:7em; height:7.6em; font-size:1em }
.im-dog * { transition:transform .3s }
/* ground shadow */
.im-dog:before { content:""; position:absolute; bottom:-.4em; left:.8em; width:5.6em; height:.9em; border-radius:100%; background:rgba(0,0,0,.18) }
.im-dog .tail { position:absolute; bottom:1.4em; left:-.4em; width:2.6em; height:.9em; border-radius:100%; background:var(--dog-coat); transform-origin:right center; animation:wag .7s ease-in-out infinite alternate }
.im-dog:hover .tail { animation-duration:.25s }
.im-dog .body { position:absolute; bottom:0; left:1.1em; width:4.4em; height:4.8em; border-radius:52% 52% 46% 46% / 62% 62% 38% 38%; background:var(--dog-coat) }
/* white chest running down to the paws */
.im-dog .chest { position:absolute; bottom:.1em; left:2.5em; width:2.4em; height:3.6em; border-radius:50% 50% 40% 40%; background:#fff }
.im-dog .paw { position:absolute; bottom:0; width:1.1em; height:.8em; border-radius:.5em .5em .3em .3em; background:#fff }
.im-dog .paw.one { left:2.4em }
.im-dog .paw.two { left:3.7em }
.im-dog .head { position:absolute; top:.5em; left:2em; width:4em; height:3.6em; border-radius:50% 50% 46% 46%; background:var(--dog-coat) }
/* blaze down the middle of the face */
.im-dog .head:before { content:""; position:absolute; top:.1em; left:1.5em; width:1.05em; height:2.4em; border-radius:.5em; background:#fff }
.im-dog .muzzle { position:absolute; bottom:-.5em; left:.9em; width:2.2em; height:1.7em; border-radius:50% 50% 45% 45%; background:#fff }
.im-dog .nose { position:absolute; top:.15em; left:.75em; width:.7em; height:.55em; border-radius:50%; background:#2d2f30 }
.im-dog .im-eye { position:absolute; top:1.25em; width:.8em; height:.8em; border-radius:100%; background:#fff; overflow:hidden }
.im-dog .im-eye:before { content:""; position:absolute; right:18%; bottom:18%; width:55%; height:55%; border-radius:100%; background:#000; transition:all .3s }
.im-dog .im-eye.one { left:.75em }
.im-dog .im-eye.two { right:.75em }
.im-dog:hover .im-eye:before { right:35%; bottom:30% }
/* pricked ears */
.im-dog .im-ear { position:absolute; top:-.7em; width:1.2em; height:1.5em; background:var(--dog-coat); clip-path:polygon(50% 0, 100% 100%, 0 100%) }
.im-dog .im-ear.one { left:.1em; transform:rotate(-16deg) }
.im-dog .im-ear.two { right:.1em; transform:rotate(16deg) }
.im-dog:hover .im-ear { transform:rotate(0deg) }
@keyframes wag { from { transform:rotate(-16deg) } to { transform:rotate(14deg) } }
`;

/* border collie, sitting — the one who lost the flock */
export default function Dog({
  coatColor = '#2d2f30',
  scale = 1,
  facing = 'right',
}: Props) {
  return (
    <div>
      <style>{css}</style>
      <div
        className="im-dog"
        style={
          {
            '--dog-coat': coatColor,
            fontSize: `${scale}em`,
            transform: facing === 'left' ? 'scaleX(-1)' : undefined,
          } as CSSProperties
        }
      >
        <div className="tail"></div>
        <div className="body">
          <div className="chest"></div>
        </div>
        <div className="paw one"></div>
        <div className="paw two"></div>
        <div className="head">
          <div className="im-ear one"></div>
          <div className="im-ear two"></div>
          <div className="im-eye one"></div>
          <div className="im-eye two"></div>
          <div className="muzzle">
            <div className="nose"></div>
          </div>
        </div>
      </div>
    </div>
  );
}
