import wave,numpy as np
with wave.open('/tmp/plummo-ambiance-v2.wav') as w:
 sr=w.getframerate(); x=np.frombuffer(w.readframes(w.getnframes()),dtype='<i2').reshape(-1,2).astype(float)/32768
n=len(x); blocks=128; edges=np.linspace(0,n,blocks+1).astype(int)
rms=np.array([np.sqrt(np.mean(x[edges[i]:edges[i+1]]**2)) for i in range(blocks)])
# Level broad musical passages; circular smoothing preserves the loop junction.
loggain=np.log(np.median(rms)/rms)
for _ in range(1): loggain=(np.roll(loggain,1)+2*loggain+np.roll(loggain,-1))/4
centers=(edges[:-1]+edges[1:])/2
gain=np.exp(np.interp(np.arange(n),np.r_[centers[-1]-n,centers,centers[0]+n],np.r_[loggain[-1],loggain,loggain[0]]))
y=x*gain[:,None]; y*=.78/np.abs(y).max()
with wave.open('/tmp/plummo-ambiance-finale.wav','wb') as w:
 w.setnchannels(2);w.setsampwidth(2);w.setframerate(sr);w.writeframes(np.round(y*32767).astype('<i2').tobytes())
print('Duration:',n/sr,'RMS range before dB:',20*np.log10(rms.max()/rms.min()))
new=np.array([np.sqrt(np.mean(y[edges[i]:edges[i+1]]**2)) for i in range(blocks)])
print('RMS range after dB:',20*np.log10(new.max()/new.min()),'boundary jump:',np.abs(y[0]-y[-1]).max(),'peak:',np.abs(y).max())
