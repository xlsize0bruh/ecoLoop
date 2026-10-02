export const iconPaths = {
  loop: '<path d="M7 15c-6-5 0-13 6-7l2 2c4 4 9-1 5-5M17 9c6 5 0 13-6 7l-2-2c-4-4-9 1-5 5"/>',
  market: '<path d="M3 10h18M5 10v10h14V10M8 20v-6h5v6M3 10l2-6h14l2 6M8 4l-1 6M16 4l1 6"/>',
  projects: '<path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v10l9 5 9-5V8M12 13v10"/>',
  box: '<path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v10l9 5 9-5V8M12 13v10M7 6l9 5"/>',
  exchange: '<path d="M4 7h15l-4-4M20 17H5l4 4M19 7l-4 4M5 17l4-4"/>',
  wallet: '<path d="M20 7H5a2 2 0 0 1 0-4h13v4M4 5v14a2 2 0 0 0 2 2h14V7M20 11h-6v6h6M16 14h1"/>',
  leaf: '<path d="M19 3c1 7 3 13-4 16-6 2-12-4-9-9C9 5 13 8 19 3ZM5 21l10-10"/>',
  shield: '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3ZM8 12l3 3 5-6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>', arrow: '<path d="M5 12h14m-5-5 5 5-5 5"/>',
  arrowUp: '<path d="M6 18 18 6M6 6h12v12"/>', search: '<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/>',
  bell: '<path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3ZM10 21h4"/>',
  close: '<path d="m6 6 12 12M6 18 18 6"/>', chevron: '<path d="m8 5 7 7-7 7"/>',
  check: '<path d="m5 12 4 4L19 6"/>', clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  pin: '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
  coins: '<circle cx="9" cy="9" r="6"/><path d="M15 8a6 6 0 1 1-7 7M9 6v6M7 8h3M14 13v4"/>',
  chat: '<path d="M21 11a9 9 0 0 1-9 9H4l-2 2 1-8a9 9 0 1 1 18-3Z"/><path d="M7 10h10M7 14h6"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>', user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  logout: '<path d="M9 4H4v16h5M9 12h12l-4-4M21 12l-4 4"/>',
  edit: '<path d="m15 4 5 5M4 20l1-6L16 3l5 5L10 19l-6 1Z"/>',
  photo: '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="2"/><path d="m3 18 6-6 4 4 3-3 5 5"/>',
  spark: '<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/>',
};
export function icon(name, cls = '') { return `<svg class="icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${iconPaths[name] || iconPaths.box}</svg>`; }

const art = {
  cardboard_sheet: '<path d="m72 146 104-53 97 41-105 58Z" fill="#bd8450"/><path d="m72 136 104-53 97 41-105 58Z" fill="#deb077" stroke="#9a6c43" stroke-width="2"/><path d="m72 124 104-53 97 41-105 58Z" fill="#e9c595" stroke="#ad865c" stroke-width="2"/><path d="m72 113 104-53 97 41-105 58Z" fill="#f0d6aa" stroke="#b4966f" stroke-width="2"/><path d="m128 84 99 42M116 135l108-54" stroke="#b0906d" stroke-width="2"/>',
  cardboard_tube: '<g stroke="#b88a54" stroke-width="2"><path d="M83 65h39v109H83z" fill="#d7b382"/><ellipse cx="102.5" cy="65" rx="19.5" ry="10" fill="#e8c89d"/><ellipse cx="102.5" cy="65" rx="12" ry="6" fill="#976c41"/><path d="M139 47h42v118h-42z" fill="#e2bd8d"/><ellipse cx="160" cy="47" rx="21" ry="11" fill="#f1d4a6"/><ellipse cx="160" cy="47" rx="13" ry="7" fill="#94704d"/><path d="M193 83h45v102h-45z" fill="#cfaa75"/><ellipse cx="215.5" cy="83" rx="22.5" ry="11" fill="#f0d2a1"/><ellipse cx="215.5" cy="83" rx="14" ry="6" fill="#97734d"/></g>',
  fabric: '<path d="m75 95 125-40 59 95-126 42Z" fill="#819179"/><path d="m66 91 131-44 49 73-128 43Z" fill="#afc09c"/><path d="m92 68 73-10 54 104-73 10Z" fill="#eed19e"/><path d="m108 66 49 103M125 62l49 104M142 60l48 102M115 91l71-9M126 117l72-11M139 143l70-11" stroke="#c6a270" stroke-width="2"/><path d="M202 150c-49-52 58-76 41-29-17 37-77 37-23-18" fill="none" stroke="#846a4e" stroke-width="5"/>',
  book: '<path d="m79 79 120-22 35 103-124 24Z" fill="#294f48"/><path d="m80 68 115-23 34 103-115 23Z" fill="#608574"/><path d="m91 71 22 88 108-21" fill="none" stroke="#dce1c7" stroke-width="6"/><path d="m121 91 72-14M132 111l63-12" stroke="#dfe6cf" stroke-width="3"/><path d="m115 144 116 6-17 37-116-8Z" fill="#dfa77c"/><path d="m100 175 111 7" stroke="#f9edd7" stroke-width="5"/><path d="m92 72 116-21" stroke="#386050" stroke-width="4"/>',
  stationery: '<rect x="72" y="106" width="187" height="68" rx="15" fill="#789997" stroke="#476767" stroke-width="3"/><rect x="78" y="112" width="175" height="57" rx="12" fill="#a9c2b2"/><path d="m103 53 15-3 23 100-15 3Z" fill="#ddb86b"/><path d="m103 53 6-18 9 15" fill="#d8c0a0"/><path d="m153 51 16 4-25 97-16-4Z" fill="#d5967d"/><path d="m169 55-1-21-15 17" fill="#dcc6aa"/><path d="m203 61 17 7-32 79-17-7Z" fill="#efe4c4"/><path d="m202 65-29 75M212 74l-6-2M207 85l-6-2M202 96l-6-2M197 107l-6-2M192 118l-6-2" stroke="#9b967e" stroke-width="2"/><rect x="101" y="154" width="128" height="5" rx="2" fill="#73958a"/>',
  paper: '<path d="m82 66 142 9-10 118-143-13Z" fill="#d0b986"/><path d="m84 58 138 10-10 116-138-10Z" fill="#fffaf0" stroke="#cfbe9d" stroke-width="2"/><path d="m87 50 137 11-12 111-137-10Z" fill="#f4eddc" stroke="#d9cdb5" stroke-width="2"/><path d="m104 81 88 7M102 98l88 7M100 115l71 6" stroke="#cfbd97" stroke-width="2"/><path d="m103 150 56-73 9 7-56 73-12 7Z" fill="#7e9b75"/>',
  plastic: '<path d="M135 45h54v20l15 24v86c0 17-83 17-83 0V89l14-24Z" fill="#bfd1ba" stroke="#7f9c89" stroke-width="2"/><rect x="135" y="35" width="54" height="18" rx="4" fill="#658872"/><path d="M128 113c31 11 49 9 69 0v40c-26 10-47 10-69 0Z" fill="#dce6cd"/><path d="M151 82v19M131 94v71" stroke="#eef4e8" stroke-width="5" stroke-linecap="round"/>',
  wood: '<path d="m63 99 151-40 49 77-152 43Z" fill="#cfa577"/><path d="m66 87 151-40 46 77-151 43Z" fill="#e4bd8b"/><path d="m74 100 143-39M81 113l143-39M90 127l143-39M99 141l143-39" stroke="#c49660" stroke-width="2"/><ellipse cx="156" cy="107" rx="24" ry="9" fill="none" stroke="#c49660" stroke-width="2"/>',
  organiser: '<path d="m62 160 111-38 101 26-109 46Z" fill="#b5936a"/><path d="M74 93h58v64l-58 19Z" fill="#b7b58d"/><path d="m74 93 28-10 58 6-28 4Z" fill="#e4d6b1"/><path d="m132 93 28-4v62l-28 6Z" fill="#8d9473"/><path d="M166 87h49v71l-49 17Z" fill="#d2b18e"/><ellipse cx="190.5" cy="87" rx="24.5" ry="10" fill="#e9c9a5"/><ellipse cx="190.5" cy="87" rx="16" ry="6" fill="#9e7956"/><path d="m88 107 1-62 8-1 4 64" fill="#d2a869"/><path d="m112 103 9-56 8 1-5 56" fill="#739579"/><path d="m179 89 4-47 7 1 1 47" fill="#71948f"/><path d="m196 85 16-31 6 4-12 31" fill="#d89575"/><path d="m218 130 33-7 9 32-31 12Z" fill="#f2dfa9"/><path d="m230 135 15-4M233 143l15-4" stroke="#b49d6e" stroke-width="2"/>',
  display: '<path d="m62 73 53-21v122l-53-15Z" fill="#9bad8c"/><path d="M115 52h113v122H115Z" fill="#c5d0aa"/><path d="m228 52 38 25v83l-38 14Z" fill="#8d9f78"/><rect x="132" y="67" width="79" height="29" rx="3" fill="#f6ebcb"/><rect x="132" y="110" width="34" height="42" fill="#ede2bb"/><rect x="178" y="110" width="33" height="42" fill="#f4e8cc"/><path d="M140 77h57M146 85h43M139 123h20M185 125h19M185 136h19" stroke="#b3af87" stroke-width="3"/>',
  banner: '<path d="M39 68c79 74 181 67 248 0" fill="none" stroke="#806f59" stroke-width="3"/><path d="m65 90 46 21-42 55Z" fill="#759b84"/><path d="m129 118 49 4-19 65Z" fill="#dab484"/><path d="m195 116 42-15 6 63Z" fill="#c88773"/>',
};
export function illustration(type = 'other', label = '') {
  const artwork = art[type] || art.organiser;
  return `<svg viewBox="0 0 330 220" class="illustration" role="img" aria-label="${label || 'Illustration of reusable items'}"><ellipse cx="165" cy="192" rx="97" ry="10" fill="#234a3c" opacity=".07"/>${artwork}</svg>`;
}
