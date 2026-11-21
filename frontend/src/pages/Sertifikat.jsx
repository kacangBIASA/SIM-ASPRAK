import { useState } from 'react';
import { useSubJudul } from '../subjudul';
import NomorSurat from './NomorSurat';
import TandaTanganStempel from './TandaTanganStempel';
import AturPosisiStempel from './AturPosisiStempel';
import Penerbitan from './Penerbitan';

/* Sertifikat (laboran): tab Nomor surat (US11) dan Tanda tangan dan stempel (US10, termasuk layar Atur posisi). Tab Penerbitan (US12). */
const TAB = [['nomor', 'Nomor surat'], ['ttd', 'Tanda tangan dan stempel'], ['terbit', 'Penerbitan']];

export default function Sertifikat() {
  const [tab, setTab] = useState('nomor');
  const [posisi, setPosisi] = useState(false);
  useSubJudul(tab === 'nomor' ? '' : tab === 'terbit' ? 'Penerbitan' : posisi ? 'Tanda Tangan dan Stempel / Atur Posisi' : 'Tanda Tangan dan Stempel');

  return (
    <>
      <div className="tab-bar" role="tablist">
        {TAB.map(([k, label]) => (
          <button key={k} role="tab" aria-selected={tab === k} className={tab === k ? 'aktif' : ''} onClick={() => { setTab(k); setPosisi(false); }}>{label}</button>
        ))}
      </div>
      {tab === 'nomor' ? <NomorSurat /> : tab === 'terbit' ? <Penerbitan /> : posisi ? <AturPosisiStempel onKembali={() => setPosisi(false)} /> : <TandaTanganStempel onAturPosisi={() => setPosisi(true)} />}
    </>
  );
}
